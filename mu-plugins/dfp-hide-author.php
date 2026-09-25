<?php
/**
 * Plugin Name: DIM Author Privacy
 * Description: Stops WordPress from exposing usernames and staff emails on the front end. Blog posts keep a clean author byline; pages and every other post type show no author. Works with Rank Math, Yoast SEO, or no SEO plugin.
 * Version:     1.0.1
 * Author:      Dental Implant Machine
 * License:     GPL-2.0-or-later
 *
 * Install on Pressable (all sites): My Pressable -> Account -> MU Plugins, add the raw URL of this file,
 * tick "Existing sites" and "Future sites". To ship a new version, update the file at that URL and click Update.
 * Install on one site: copy to wp-content/mu-plugins/dim-hide-author.php.
 *
 * What it covers for logged-out visitors:
 *  1. ?author=N and ?author_name= enumeration      -> 301 to home
 *  2. /author/slug/ archives + author links        -> home, unless the user has blog posts, a safe name,
 *                                                     and a slug that isn't their login
 *  3. REST /wp/v2/users (list, single, search)      -> closed (404) for logged-out visitors
 *  4. oEmbed author_name / author_url               -> removed on non-blog content
 *  5. Rank Math + Yoast schema, "Written by" tags   -> removed on non-blog content
 *  6. Theme bylines on pages                        -> blanked
 *  7. Author sitemaps                               -> core one removed; Yoast lists public authors only
 *  8. Safety net: any public display name that is an email or equals the login name is replaced with the site name.
 *
 * Filters:
 *  dim_author_visible_post_types  (array)  Post types that may show an author. Default [ 'post' ].
 *  dim_author_public_name         (string) Name used when a display name is unsafe. Default: site title.
 */

defined( 'ABSPATH' ) || exit;

define( 'DIM_AUTHOR_PRIVACY_VERSION', '1.2.0' );

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/** Post types that may show an author (default: blog posts only). */
function dim_ha_visible_types() {
	return (array) apply_filters( 'dim_author_visible_post_types', array( 'post' ) );
}

/** True when this post type may show its author. */
function dim_ha_type_allowed( $post_type ) {
	return in_array( $post_type, dim_ha_visible_types(), true );
}

/** True for front-end requests made by someone who can't manage users. */
function dim_ha_is_public_request() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	return ! current_user_can( 'list_users' );
}

/** True when the current front-end request is a single page/CPT that should hide the author. */
function dim_ha_hide_here() {
	if ( is_admin() || ! is_singular() ) {
		return false;
	}
	$post = get_queried_object();
	return $post instanceof WP_Post && ! dim_ha_type_allowed( $post->post_type );
}

/** True when the user has published at least one post in a visible post type. */
function dim_ha_user_is_blog_author( $user_id ) {
	return (int) count_user_posts( (int) $user_id, dim_ha_visible_types(), true ) > 0;
}

/** Public-safe name to use instead of an email or login name. */
function dim_ha_public_name() {
	$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	return (string) apply_filters( 'dim_author_public_name', '' !== $name ? $name : 'Our Team' );
}

/** True when a display name would leak an email address or the login username. */
function dim_ha_name_is_unsafe( $name, $user ) {
	$name = trim( (string) $name );
	if ( '' === $name || false !== strpos( $name, '@' ) ) {
		return true;
	}
	if ( $user instanceof WP_User ) {
		if ( 0 === strcasecmp( $name, $user->user_login ) || 0 === strcasecmp( $name, $user->user_email ) ) {
			return true;
		}
	}
	return false;
}

/** True when the author slug (user_nicename) is just the login name, so the URL would reveal it. */
function dim_ha_slug_is_login( $user ) {
	return $user instanceof WP_User && $user->user_nicename === sanitize_title( $user->user_login );
}

/**
 * True when a user may have a public author identity (archive page, slug, author link):
 * they wrote blog posts, their display name is safe, and their slug doesn't reveal their login.
 */
function dim_ha_author_is_public( $user_id ) {
	$user = get_userdata( (int) $user_id );
	return $user
		&& dim_ha_user_is_blog_author( $user->ID )
		&& ! dim_ha_name_is_unsafe( $user->display_name, $user )
		&& ! dim_ha_slug_is_login( $user );
}

/** Returns a safe public version of a user's display name. */
function dim_ha_safe_name( $name, $user_id ) {
	$user = $user_id ? get_userdata( (int) $user_id ) : false;
	return dim_ha_name_is_unsafe( $name, $user ) ? dim_ha_public_name() : $name;
}

/**
 * Remove author data from a schema graph (works for both Rank Math and Yoast).
 * Drops standalone Person / ProfilePage entities (keeps a Person that is the site publisher)
 * and drops the "author" property from every top-level entity.
 */
function dim_ha_clean_graph( $graph ) {
	if ( ! is_array( $graph ) || empty( $graph ) ) {
		return $graph;
	}
	$is_list = array_keys( $graph ) === range( 0, count( $graph ) - 1 );

	$keep = array();
	foreach ( $graph as $entity ) {
		if ( is_array( $entity ) && ! empty( $entity['publisher']['@id'] ) ) {
			$keep[] = $entity['publisher']['@id'];
		}
	}

	foreach ( $graph as $key => $entity ) {
		if ( ! is_array( $entity ) ) {
			continue;
		}
		$types     = isset( $entity['@type'] ) ? (array) $entity['@type'] : array();
		$id        = isset( $entity['@id'] ) ? $entity['@id'] : '';
		$is_person = in_array( 'Person', $types, true ) && ! in_array( 'Organization', $types, true );

		if ( in_array( 'ProfilePage', $types, true ) || ( $is_person && ! in_array( $id, $keep, true ) ) ) {
			unset( $graph[ $key ] );
			continue;
		}
		unset( $graph[ $key ]['author'] );
	}

	return $is_list ? array_values( $graph ) : $graph;
}

/* -------------------------------------------------------------------------
 * 1 + 2. ?author= enumeration and author archives
 * ---------------------------------------------------------------------- */

// Runs before WordPress's canonical redirect (priority 10), so no name or slug is revealed.
add_action( 'template_redirect', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	// $_GET only, so front-end forms with a field named "author" keep working.
	// POST-based lookups are still caught by the is_author() check below.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check.
	if ( isset( $_GET['author'] ) || isset( $_GET['author_name'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
	if ( is_author() && ! dim_ha_author_is_public( get_queried_object_id() ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 0 );

// Author links in themes/bylines: point to the home page when the author has no public identity.
add_filter( 'author_link', function ( $link, $author_id ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $link;
	}
	return dim_ha_author_is_public( $author_id ) ? $link : home_url( '/' );
}, 99, 2 );

/* -------------------------------------------------------------------------
 * 3. REST API users
 * ---------------------------------------------------------------------- */

// Close the REST user endpoints completely for logged-out visitors (v1.2.0).
// Logged-in users (block editor, Elementor) keep full access.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
}, 99 );

// Defense in depth for logged-in users without list_users (e.g. subscribers):
// they only see blog authors, never with an email/login as the name.
// List and search: visitors only see users who wrote blog posts.
add_filter( 'rest_user_query', function ( $args ) {
	if ( dim_ha_is_public_request() ) {
		$args['has_published_posts'] = dim_ha_visible_types();
	}
	return $args;
}, 99 );

// Single user (/wp/v2/users/3): same rule.
add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {
	if ( ! dim_ha_is_public_request() ) {
		return $response;
	}
	if ( preg_match( '#^/wp/v2/users/(\d+)#', $request->get_route(), $m ) && ! dim_ha_user_is_blog_author( $m[1] ) ) {
		return new WP_Error( 'rest_user_invalid_id', 'Invalid user ID.', array( 'status' => 404 ) );
	}
	return $response;
}, 99, 3 );

// Never return an email or login name as "name" (also covers _embed=author).
add_filter( 'rest_prepare_user', function ( $response, $user ) {
	if ( dim_ha_is_public_request() && $response instanceof WP_REST_Response ) {
		$data = $response->get_data();
		if ( isset( $data['name'] ) ) {
			$data['name'] = dim_ha_safe_name( $data['name'], $user->ID );
		}
		// Don't reveal a slug that equals the login name.
		if ( ! dim_ha_author_is_public( $user->ID ) ) {
			unset( $data['slug'] );
			if ( isset( $data['link'] ) ) {
				$data['link'] = home_url( '/' );
			}
		}
		$response->set_data( $data );
	}
	return $response;
}, 99, 2 );

/* -------------------------------------------------------------------------
 * 4. oEmbed
 * ---------------------------------------------------------------------- */

add_filter( 'oembed_response_data', function ( $data, $post ) {
	if ( ! $post instanceof WP_Post ) {
		return $data;
	}
	if ( ! dim_ha_type_allowed( $post->post_type ) ) {
		unset( $data['author_name'], $data['author_url'] );
	} elseif ( isset( $data['author_name'] ) ) {
		$data['author_name'] = dim_ha_safe_name( $data['author_name'], $post->post_author );
	}
	return $data;
}, 99, 2 );

/* -------------------------------------------------------------------------
 * 5. SEO plugins
 * ---------------------------------------------------------------------- */

// Rank Math: schema, "Written by / Time to read", article:author.
add_filter( 'rank_math/json_ld', function ( $data ) {
	return dim_ha_hide_here() ? dim_ha_clean_graph( $data ) : $data;
}, 99 );
add_filter( 'rank_math/opengraph/slack_enhanced_sharing', function ( $data ) {
	return dim_ha_hide_here() ? false : $data;
}, 99 );
add_filter( 'rank_math/opengraph/facebook/article_author', function ( $value ) {
	return dim_ha_hide_here() ? false : $value;
}, 99 );

// Yoast SEO: schema, meta author, "Written by", article:author.
add_filter( 'wpseo_schema_graph', function ( $graph ) {
	return dim_ha_hide_here() ? dim_ha_clean_graph( $graph ) : $graph;
}, 99 );
add_filter( 'wpseo_meta_author', function ( $value ) {
	return dim_ha_hide_here() ? false : $value;
}, 99 );
add_filter( 'wpseo_enhanced_slack_data', function ( $data ) {
	return dim_ha_hide_here() ? array() : $data;
}, 99 );
add_filter( 'wpseo_opengraph_author_facebook', function ( $value ) {
	return dim_ha_hide_here() ? false : $value;
}, 99 );

/* -------------------------------------------------------------------------
 * 6. Theme bylines + 8. display-name safety net (front end only)
 * ---------------------------------------------------------------------- */

// get_the_author() / the_author(): blank on pages/CPTs, safe name on blog posts.
add_filter( 'the_author', function ( $name ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $name;
	}
	$post = get_post();
	if ( $post && ! dim_ha_type_allowed( $post->post_type ) ) {
		return '';
	}
	return dim_ha_safe_name( $name, $post ? $post->post_author : 0 );
}, 99 );

// get_the_author_meta( 'display_name' ): used by Rank Math, Yoast, oEmbed and many themes.
add_filter( 'get_the_author_display_name', function ( $value, $user_id ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $value;
	}
	return dim_ha_safe_name( $value, $user_id );
}, 99, 2 );

/* -------------------------------------------------------------------------
 * 7. Core XML sitemap
 * ---------------------------------------------------------------------- */

// Author sitemaps add no SEO value for practice sites and list author slugs, so remove the core one.
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 99, 2 );

// Yoast author sitemap: drop users without a public author identity.
add_filter( 'wpseo_sitemap_exclude_author', function ( $users ) {
	return array_values( array_filter( (array) $users, function ( $user ) {
		return $user instanceof WP_User && dim_ha_author_is_public( $user->ID );
	} ) );
}, 99 );
