<?php
/**
 * The built-in provider set (PLAN.md §4.2).
 *
 * Descriptors are data, not classes (§4.1). WordPress-free: the translate
 * callable is injected, identity outside WordPress. Provider names are
 * proper nouns and are never translated (§9.15).
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Providers\Builtin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ships enough that a typical site needs no configuration. Every descriptor
 * prefers a privacy-preserving load target where one exists: measured on the
 * source site, youtube-nocookie.com sets 0 cookies where youtube.com sets 5.
 */
final class Descriptors {

	/**
	 * @param callable|null $translate Maps English strings to the site language.
	 * @return array[] Provider descriptors, most specific first.
	 */
	public static function all( ?callable $translate = null ): array {
		$t = $translate ?? static function ( string $text ): string {
			return $text;
		};

		return array(
			array(
				'id'               => 'youtube',
				'kind'             => 'video',
				'label'            => 'YouTube',
				'match'            => array(
					'iframe_host' => array(
						'youtube.com',
						'www.youtube.com',
						'm.youtube.com',
						'youtube-nocookie.com',
						'www.youtube-nocookie.com',
						'youtu.be',
					),
					'iframe_path' => '#^/embed/(?P<id>[A-Za-z0-9_-]{6,20})#',
				),
				// Data minimisation: measured 0 cookies vs 5 on the default host.
				'load_host'        => 'www.youtube-nocookie.com',
				'load_path'        => '/embed/{id}',
				'fallback'         => 'https://www.youtube.com/watch?v={id}',
				'scrub_hint_hosts' => array( 'i.ytimg.com', 's.ytimg.com', 'img.youtube.com', 'yt3.ggpht.com' ),
				'privacy_url'      => 'https://policies.google.com/privacy',
				'controller'       => 'Google Ireland Limited, Dublin, Ireland',
				'note'             => $t( 'Loading this video contacts YouTube (Google), which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load video from YouTube' ),
				'aspect'           => '16:9',
				'iframe_allow'     => 'accelerometer; encrypted-media; gyroscope; picture-in-picture; web-share',
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'vimeo',
				'kind'             => 'video',
				'label'            => 'Vimeo',
				'match'            => array(
					'iframe_host' => array( 'player.vimeo.com' ),
					'iframe_path' => '#^/video/(?P<id>[0-9]+)#',
				),
				// Keep the original URL (unlisted videos need their ?h= hash)
				// and merge dnt=1, which suppresses Vimeo's analytics.
				'load_query'       => array( 'dnt' => '1' ),
				'fallback'         => 'https://vimeo.com/{id}',
				'scrub_hint_hosts' => array( 'i.vimeocdn.com', 'f.vimeocdn.com' ),
				'privacy_url'      => 'https://vimeo.com/privacy',
				'controller'       => 'Vimeo.com, Inc., New York, USA',
				'note'             => $t( 'Loading this video contacts Vimeo, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load video from Vimeo' ),
				'aspect'           => '16:9',
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'google-maps',
				'kind'             => 'map',
				'label'            => 'Google Maps',
				'match'            => array(
					'iframe_host' => array( 'www.google.com', 'google.com', 'maps.google.com' ),
					// Three shapes in the field: /maps/embed (Share → Embed a
					// map), /maps/d/embed (My Maps), and the legacy
					// /maps?q=…&output=embed which is bare /maps as a path.
					'iframe_path' => '#^/maps(?:/|$)#',
				),
				// No privacy-preserving variant exists; gate only. The README
				// suggests OpenStreetMap as the replacement.
				// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- nothing is loaded from these hosts; they are listed so the plugin can REMOVE preconnect/dns-prefetch hints pointing at them (resource-hint scrubbing).
				'scrub_hint_hosts' => array( 'maps.gstatic.com', 'maps.googleapis.com' ), // REMOVED from resource hints — never requested by this plugin.
				'privacy_url'      => 'https://policies.google.com/privacy',
				'controller'       => 'Google Ireland Limited, Dublin, Ireland',
				'note'             => $t( 'Loading this map contacts Google Maps, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load map from Google Maps' ),
				'strategy'         => 'iframe',
			),
			array(
				'id'          => 'openstreetmap',
				'kind'        => 'map',
				'label'       => 'OpenStreetMap',
				'match'       => array(
					'iframe_host' => array( 'www.openstreetmap.org', 'openstreetmap.org' ),
					'iframe_path' => '#^/export/embed#',
				),
				'privacy_url' => 'https://osmfoundation.org/wiki/Privacy_Policy',
				'controller'  => 'OpenStreetMap Foundation, Cambridge, UK',
				'note'        => $t( 'Loading this map contacts OpenStreetMap, which receives your IP address and which page you are on.' ),
				'action'      => $t( 'Load map from OpenStreetMap' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'               => 'spotify',
				'kind'             => 'audio',
				'label'            => 'Spotify',
				'match'            => array(
					'iframe_host' => array( 'open.spotify.com' ),
					'iframe_path' => '#^/embed/(?P<type>track|album|playlist|episode|show|artist)/(?P<id>[A-Za-z0-9]+)#',
				),
				'fallback'         => 'https://open.spotify.com/{type}/{id}',
				'scrub_hint_hosts' => array( 'i.scdn.co' ),
				'privacy_url'      => 'https://www.spotify.com/legal/privacy-policy/',
				'controller'       => 'Spotify AB, Stockholm, Sweden',
				'note'             => $t( 'Loading this player contacts Spotify, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load player from Spotify' ),
				'strategy'         => 'iframe',
			),
			array(
				'id'          => 'soundcloud',
				'kind'        => 'audio',
				'label'       => 'SoundCloud',
				'match'       => array(
					'iframe_host' => array( 'w.soundcloud.com' ),
					'iframe_path' => '#^/player#',
				),
				'privacy_url' => 'https://soundcloud.com/pages/privacy',
				'controller'  => 'SoundCloud Global Limited & Co. KG, Berlin, Germany',
				'note'        => $t( 'Loading this player contacts SoundCloud, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'      => $t( 'Load player from SoundCloud' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'apple-music',
				'kind'        => 'audio',
				'label'       => 'Apple Music',
				'match'       => array(
					'iframe_host' => array( 'embed.music.apple.com', 'embed.podcasts.apple.com' ),
				),
				'privacy_url' => 'https://www.apple.com/legal/privacy/',
				'controller'  => 'Apple Distribution International Ltd., Cork, Ireland',
				'note'        => $t( 'Loading this player contacts Apple, which receives your IP address and which page you are on.' ),
				'action'      => $t( 'Load player from Apple' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'google-calendar',
				'kind'        => 'calendar',
				'label'       => 'Google Calendar',
				'match'       => array(
					'iframe_host' => array( 'calendar.google.com' ),
					'iframe_path' => '#^/calendar/embed#',
				),
				'privacy_url' => 'https://policies.google.com/privacy',
				'controller'  => 'Google Ireland Limited, Dublin, Ireland',
				'note'        => $t( 'Loading this calendar contacts Google, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'      => $t( 'Load calendar from Google' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'google-forms',
				'kind'        => 'form',
				'label'       => 'Google Forms',
				'match'       => array(
					'iframe_host' => array( 'docs.google.com' ),
					'iframe_path' => '#^/forms/#',
				),
				'privacy_url' => 'https://policies.google.com/privacy',
				'controller'  => 'Google Ireland Limited, Dublin, Ireland',
				'note'        => $t( 'Loading this form contacts Google, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'      => $t( 'Load form from Google' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'matterport',
				'kind'        => '3d',
				'label'       => 'Matterport',
				'match'       => array(
					'iframe_host' => array( 'my.matterport.com' ),
					'iframe_path' => '#^/show#',
				),
				'privacy_url' => 'https://matterport.com/legal/privacy-policy',
				'controller'  => 'Matterport, Inc., Sunnyvale, USA',
				'note'        => $t( 'Loading this tour contacts Matterport, which receives your IP address and which page you are on.' ),
				'action'      => $t( 'Load tour from Matterport' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'sketchfab',
				'kind'        => '3d',
				'label'       => 'Sketchfab',
				'match'       => array(
					'iframe_host' => array( 'sketchfab.com' ),
					'iframe_path' => '#/embed#',
				),
				'privacy_url' => 'https://sketchfab.com/privacy',
				'controller'  => 'Sketchfab, Inc., New York, USA',
				'note'        => $t( 'Loading this model contacts Sketchfab, which receives your IP address and which page you are on.' ),
				'action'      => $t( 'Load model from Sketchfab' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'typeform',
				'kind'        => 'form',
				'label'       => 'Typeform',
				'match'       => array(
					'iframe_host' => array( 'form.typeform.com' ),
					'script_host' => array( 'embed.typeform.com' ),
				),
				'privacy_url' => 'https://www.typeform.com/privacy-policy/',
				'controller'  => 'Typeform S.L., Barcelona, Spain',
				'note'        => $t( 'Loading this form contacts Typeform, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'      => $t( 'Load form from Typeform' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'calendly',
				'kind'        => 'calendar',
				'label'       => 'Calendly',
				'match'       => array(
					'iframe_host' => array( 'calendly.com' ),
					'script_host' => array( 'assets.calendly.com' ),
				),
				'privacy_url' => 'https://calendly.com/privacy',
				'controller'  => 'Calendly LLC, Atlanta, USA',
				'note'        => $t( 'Loading this scheduler contacts Calendly, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'      => $t( 'Load scheduler from Calendly' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'                 => 'strava',
				'kind'               => 'social',
				'label'              => 'Strava',
				'match'              => array(
					'script_host' => array( 'strava-embeds.com', 'www.strava-embeds.com' ),
				),
				'privacy_url'        => 'https://www.strava.com/legal/privacy',
				'controller'         => 'Strava, Inc., San Francisco, USA',
				'note'               => $t( 'Loading this activity contacts Strava, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'             => $t( 'Load activity from Strava' ),
				'strategy'           => 'script',
				'companion_class'    => array( 'strava-embed-placeholder' ),
				// The companion div carries data-embed-type/data-embed-id;
				// the human page is derivable from them.
				'companion_fallback' => static function ( array $attributes ) {
					$type = isset( $attributes['data-embed-type'] ) && is_string( $attributes['data-embed-type'] )
						? $attributes['data-embed-type'] : '';
					$id   = isset( $attributes['data-embed-id'] ) && is_string( $attributes['data-embed-id'] )
						? $attributes['data-embed-id'] : '';
					$map  = array(
						'activity' => 'activities',
						'segment'  => 'segments',
						'route'    => 'routes',
						'club'     => 'clubs',
					);
					if ( '' === $id || ! isset( $map[ $type ] ) || ! preg_match( '/^[0-9]+$/', $id ) ) {
						return null;
					}
					return 'https://www.strava.com/' . $map[ $type ] . '/' . rawurlencode( $id );
				},
			),
			array(
				'id'               => 'twitter',
				'kind'             => 'social',
				'label'            => 'X (Twitter)',
				'match'            => array(
					'iframe_host' => array( 'platform.twitter.com', 'platform.x.com' ),
					'script_host' => array( 'platform.twitter.com', 'platform.x.com' ),
				),
				'privacy_url'      => 'https://x.com/en/privacy',
				'controller'       => 'Twitter International Unlimited Company, Dublin, Ireland',
				'note'             => $t( 'Loading this post contacts X (Twitter), which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load post from X (Twitter)' ),
				'strategy'         => 'script',
				'companion_class'  => array( 'twitter-tweet', 'twitter-timeline' ),
				'scrub_hint_hosts' => array( 'syndication.twitter.com', 'pbs.twimg.com', 'abs.twimg.com' ),
			),
			array(
				'id'               => 'instagram',
				'kind'             => 'social',
				'label'            => 'Instagram',
				'match'            => array(
					'iframe_host' => array( 'www.instagram.com', 'instagram.com' ),
					'script_host' => array( 'www.instagram.com', 'instagram.com', 'platform.instagram.com' ),
				),
				'privacy_url'      => 'https://privacycenter.instagram.com/policy',
				'controller'       => 'Meta Platforms Ireland Limited, Dublin, Ireland',
				'note'             => $t( 'Loading this post contacts Instagram (Meta), which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load post from Instagram' ),
				'strategy'         => 'script',
				'companion_class'  => array( 'instagram-media' ),
				'scrub_hint_hosts' => array( 'scontent.cdninstagram.com' ),
			),
			array(
				'id'              => 'tiktok',
				'kind'            => 'video',
				'label'           => 'TikTok',
				'match'           => array(
					'iframe_host' => array( 'www.tiktok.com' ),
					'script_host' => array( 'www.tiktok.com' ),
				),
				'privacy_url'     => 'https://www.tiktok.com/legal/privacy-policy',
				'controller'      => 'TikTok Technology Limited, Dublin, Ireland',
				'note'            => $t( 'Loading this video contacts TikTok, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'          => $t( 'Load video from TikTok' ),
				'strategy'        => 'script',
				'companion_class' => array( 'tiktok-embed' ),
			),
			array(
				'id'                 => 'facebook',
				'kind'               => 'social',
				'label'              => 'Facebook',
				'match'              => array(
					'iframe_host' => array( 'www.facebook.com', 'web.facebook.com' ),
					'script_host' => array( 'connect.facebook.net' ),
				),
				'privacy_url'        => 'https://www.facebook.com/privacy/policy/',
				'controller'         => 'Meta Platforms Ireland Limited, Dublin, Ireland',
				'note'               => $t( 'Loading this content contacts Facebook (Meta), which receives your IP address and which page you are on, and sets cookies.' ),
				'action'             => $t( 'Load content from Facebook' ),
				'strategy'           => 'script',
				// The canonical shape is <div id="fb-root"></div><script>…
				// with the .fb-post companion AFTER the script; its data-href
				// is the human page.
				'companion_class'    => array( 'fb-post', 'fb-video', 'fb-page' ),
				'companion_fallback' => static function ( array $attributes ) {
					$href = isset( $attributes['data-href'] ) && is_string( $attributes['data-href'] )
						? trim( $attributes['data-href'] ) : '';
					return preg_match( '#^https://(www|web)\.facebook\.com/#', $href ) ? $href : null;
				},
				'scrub_hint_hosts'   => array( 'staticxx.facebook.com' ),
			),
			array(
				'id'              => 'reddit',
				'kind'            => 'social',
				'label'           => 'Reddit',
				'match'           => array(
					'iframe_host' => array( 'embed.reddit.com', 'www.redditmedia.com' ),
					'script_host' => array( 'embed.reddit.com', 'embed.redditmedia.com' ),
				),
				'privacy_url'     => 'https://www.reddit.com/policies/privacy-policy',
				'controller'      => 'Reddit, Inc., San Francisco, USA',
				'note'            => $t( 'Loading this post contacts Reddit, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'          => $t( 'Load post from Reddit' ),
				'strategy'        => 'script',
				'companion_class' => array( 'reddit-embed-bq' ),
			),
			array(
				'id'          => 'giphy',
				'kind'        => 'image',
				'label'       => 'GIPHY',
				'match'       => array(
					'iframe_host' => array( 'giphy.com' ),
					'script_host' => array( 'giphy.com' ),
				),
				'privacy_url' => 'https://support.giphy.com/hc/en-us/articles/360032872931',
				'controller'  => 'Giphy, Inc., New York, USA',
				'note'        => $t( 'Loading this image contacts GIPHY, which receives your IP address and which page you are on.' ),
				'action'      => $t( 'Load image from GIPHY' ),
				'strategy'    => 'iframe',
			),

			// --- WordPress core oEmbed providers (0.11.0) ------------------
			// Each built from the live oEmbed output core would paste into
			// post content; see tests/Fixtures/<id>-{pretty,minified}.
			array(
				'id'               => 'dailymotion',
				'kind'             => 'video',
				'label'            => 'Dailymotion',
				'match'            => array(
					// geo.dailymotion.com is today's player (id in the query);
					// www.dailymotion.com/embed/video/{id} is the legacy shape
					// still sitting in older post content.
					'iframe_host'  => array( 'geo.dailymotion.com', 'www.dailymotion.com', 'dailymotion.com' ),
					'iframe_path'  => '#^/(?:player(?:/[a-z0-9]+)?\\.html|embed/video/(?P<id>[a-z0-9]+)|embed/playlist/[a-z0-9]+)$#i',
					'iframe_query' => '/(?:^|&)video=(?P<id>[a-z0-9]+)/i',
				),
				'fallback'         => 'https://www.dailymotion.com/video/{id}',
				'scrub_hint_hosts' => array( 's1.dmcdn.net', 's2.dmcdn.net' ),
				'privacy_url'      => 'https://legal.dailymotion.com/en/privacy-policy/',
				'controller'       => 'Dailymotion SA, Issy-les-Moulineaux, France',
				'note'             => $t( 'Loading this video contacts Dailymotion, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load video from Dailymotion' ),
				'aspect'           => '16:9',
				'iframe_allow'     => 'fullscreen; picture-in-picture; web-share',
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'ted',
				'kind'             => 'video',
				'label'            => 'TED',
				'match'            => array(
					'iframe_host' => array( 'embed.ted.com', 'embed-ssl.ted.com' ),
					// /talks/{slug}, /talks/lang/{lang}/{slug}, /embed/{slug}.
					'iframe_path' => '#^/(?:talks|embed)/(?:lang/[a-z-]+/)?(?P<id>[a-z0-9_]+)#i',
				),
				'fallback'         => 'https://www.ted.com/talks/{id}',
				'scrub_hint_hosts' => array( 'pi.tedcdn.com', 'pe.tedcdn.com' ),
				'privacy_url'      => 'https://www.ted.com/about/our-organization/our-policies-terms/privacy-policy',
				'controller'       => 'TED Conferences, LLC, New York, USA',
				'note'             => $t( 'Loading this video contacts TED, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load video from TED' ),
				'aspect'           => '16:9',
				'iframe_allow'     => 'fullscreen; encrypted-media; picture-in-picture',
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'videopress',
				'kind'             => 'video',
				'label'            => 'VideoPress',
				'match'            => array(
					// Also what WordPress.tv embeds resolve to: the same
					// player, no wordpress.tv host in the markup.
					'iframe_host' => array( 'video.wordpress.com', 'videopress.com' ),
					'iframe_path' => '#^/embed/(?P<id>[A-Za-z0-9]{6,12})#',
					// The companion loader core pastes after the iframe.
					'script_host' => array( 'v0.wordpress.com' ),
				),
				'fallback'         => 'https://videopress.com/v/{id}',
				'scrub_hint_hosts' => array( 'videos.files.wordpress.com' ),
				'privacy_url'      => 'https://automattic.com/privacy/',
				'controller'       => 'Aut O\'Mattic A8C Ireland Ltd., Dublin, Ireland',
				'note'             => $t( 'Loading this video contacts VideoPress (Automattic), which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load video from VideoPress' ),
				'aspect'           => '16:9',
				'iframe_allow'     => 'clipboard-write; presentation',
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'mixcloud',
				'kind'             => 'audio',
				'label'            => 'Mixcloud',
				'match'            => array(
					// The widget URL redirects www → player-widget; the show
					// itself is in the ?feed= query, so the widget page (a
					// standalone player) serves as the no-JS link.
					'iframe_host' => array( 'www.mixcloud.com', 'player-widget.mixcloud.com' ),
					'iframe_path' => '#^/(?:widget/iframe/?)?$#',
				),
				'scrub_hint_hosts' => array( 'thumbnailer.mixcloud.com' ),
				'privacy_url'      => 'https://www.mixcloud.com/privacy/',
				'controller'       => 'Mixcloud Limited, London, UK',
				'note'             => $t( 'Loading this player contacts Mixcloud, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load player from Mixcloud' ),
				'iframe_allow'     => 'encrypted-media; fullscreen; idle-detection; speaker-selection; web-share',
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'pocket-casts',
				'kind'             => 'audio',
				'label'            => 'Pocket Casts',
				'match'            => array(
					'iframe_host' => array( 'pca.st' ),
					'iframe_path' => '#^/embed/(?P<id>[A-Za-z0-9]+)#',
				),
				'fallback'         => 'https://pca.st/{id}',
				'scrub_hint_hosts' => array( 'static.pocketcasts.com' ),
				'privacy_url'      => 'https://support.pocketcasts.com/knowledge-base/privacy-policy/',
				'controller'       => 'Automattic Inc., San Francisco, USA',
				'note'             => $t( 'Loading this player contacts Pocket Casts (Automattic), which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load player from Pocket Casts' ),
				'strategy'         => 'iframe',
			),
			array(
				'id'               => 'pinterest',
				'kind'             => 'social',
				'label'            => 'Pinterest',
				'match'            => array(
					// Core's oEmbed output is an iframe with the pin id in the
					// query; the widget-builder form is pinit.js on the same host.
					'iframe_host'  => array( 'assets.pinterest.com' ),
					'iframe_path'  => '#^/ext/embed\\.html$#',
					'iframe_query' => '/(?:^|&)id=(?P<id>[0-9]+)/',
					'script_host'  => array( 'assets.pinterest.com' ),
				),
				'fallback'         => 'https://www.pinterest.com/pin/{id}/',
				'scrub_hint_hosts' => array( 'i.pinimg.com', 'widgets.pinterest.com', 'log.pinterest.com' ),
				'privacy_url'      => 'https://policy.pinterest.com/en/privacy-policy',
				'controller'       => 'Pinterest Europe Ltd., Dublin, Ireland',
				'note'             => $t( 'Loading this pin contacts Pinterest, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load pin from Pinterest' ),
				'strategy'         => 'iframe',
			),
			// Plugin Check's offloading sniff denylists imgur.com because
			// plugins have used it to host their own assets. Here the host is
			// the OPPOSITE: a third party this plugin exists to stop loading
			// until the visitor asks. Nothing is fetched from it, ever.
			// phpcs:disable PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- host names of an embed provider to gate, not an asset source.
			array(
				'id'               => 'imgur',
				'kind'             => 'image',
				'label'            => 'Imgur',
				'match'            => array(
					'iframe_host' => array( 'imgur.com' ),
					'script_host' => array( 's.imgur.com' ),
				),
				'privacy_url'      => 'https://imgur.com/privacy',
				'controller'       => 'Imgur, Inc., Santa Monica, USA',
				'note'             => $t( 'Loading this post contacts Imgur, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load post from Imgur' ),
				'strategy'         => 'script',
				'companion_class'  => array( 'imgur-embed-pub' ),
				'scrub_hint_hosts' => array( 'i.imgur.com' ),
			),
			// phpcs:enable PluginCheck.CodeAnalysis.Offloading.OffloadedContent
			array(
				'id'               => 'tumblr',
				'kind'             => 'social',
				'label'            => 'Tumblr',
				'match'            => array(
					'iframe_host' => array( 'embed.tumblr.com' ),
					'script_host' => array( 'assets.tumblr.com' ),
				),
				'privacy_url'      => 'https://www.tumblr.com/privacy',
				'controller'       => 'Aut O\'Mattic A8C Ireland Ltd., Dublin, Ireland',
				'note'             => $t( 'Loading this post contacts Tumblr (Automattic), which receives your IP address and which page you are on, and may set cookies.' ),
				'action'           => $t( 'Load post from Tumblr' ),
				'strategy'         => 'script',
				// The companion's child link is the human page; its data-href
				// is the embed frame, never a destination.
				'companion_class'  => array( 'tumblr-post' ),
				'scrub_hint_hosts' => array( '64.media.tumblr.com', 'static.tumblr.com' ),
			),
			array(
				'id'                 => 'bluesky',
				'kind'               => 'social',
				'label'              => 'Bluesky',
				'match'              => array(
					'iframe_host' => array( 'embed.bsky.app' ),
					'script_host' => array( 'embed.bsky.app' ),
				),
				'privacy_url'        => 'https://bsky.social/about/support/privacy-policy',
				'controller'         => 'Bluesky Social, PBC, USA',
				'note'               => $t( 'Loading this post contacts Bluesky, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'             => $t( 'Load post from Bluesky' ),
				'strategy'           => 'script',
				'companion_class'    => array( 'bluesky-embed' ),
				// at://did/app.bsky.feed.post/rkey → the post page, without the
				// ?ref_src=embed tag the companion's own link carries.
				'companion_fallback' => static function ( array $attributes ): ?string {
					$uri = isset( $attributes['data-bluesky-uri'] ) ? (string) $attributes['data-bluesky-uri'] : '';
					if ( preg_match( '#^at://([a-z0-9:.%_-]+)/app\\.bsky\\.feed\\.post/([a-z0-9]+)$#i', $uri, $m ) ) {
						return 'https://bsky.app/profile/' . $m[1] . '/post/' . $m[2];
					}
					return null;
				},
				'scrub_hint_hosts'   => array( 'cdn.bsky.app', 'video.bsky.app' ),
			),
			array(
				'id'                 => 'crowdsignal',
				'kind'               => 'form',
				'label'              => 'Crowdsignal',
				'match'              => array(
					// Polls: an external script on the legacy polldaddy host
					// (id in its path) plus a <noscript> iframe on poll.fm.
					// Surveys use an inline loader the script rule cannot see
					// (documented limitation).
					'iframe_host' => array( 'poll.fm' ),
					'script_host' => array( 'secure.polldaddy.com', 'app.crowdsignal.com' ),
					'script_path' => '#^/p/(?P<id>[0-9]+)\\.js$#',
				),
				'fallback'           => 'https://poll.fm/{id}',
				'privacy_url'        => 'https://automattic.com/privacy/',
				'controller'         => 'Aut O\'Mattic A8C Ireland Ltd., Dublin, Ireland',
				'note'               => $t( 'Loading this poll contacts Crowdsignal (Automattic), which receives your IP address and which page you are on, and may set cookies.' ),
				'action'             => $t( 'Load poll from Crowdsignal' ),
				'strategy'           => 'script',
				// Surveys: an inline loader next to <div class="pd-embed"
				// data-settings='{"domain":…,"id":…}'> — the human page is
				// https://{domain}/{id}.
				'companion_class'    => array( 'pd-embed' ),
				'companion_fallback' => static function ( array $attributes ): ?string {
					$settings = isset( $attributes['data-settings'] ) && is_string( $attributes['data-settings'] )
						? json_decode( html_entity_decode( $attributes['data-settings'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true ) : null;
					if ( is_array( $settings ) && isset( $settings['domain'], $settings['id'] )
						&& preg_match( '/^[a-z0-9-]+\\.(?:survey\\.fm|crowdsignal\\.net)$/i', (string) $settings['domain'] )
						&& preg_match( '/^[a-z0-9-]+$/i', (string) $settings['id'] ) ) {
						return 'https://' . $settings['domain'] . '/' . $settings['id'];
					}
					return null;
				},
				'scrub_hint_hosts'   => array( 'static.polldaddy.com', 'polls.polldaddy.com', 'i0.poll.fm' ),
			),
			array(
				'id'               => 'scribd',
				'kind'             => 'document',
				'label'            => 'Scribd',
				'match'            => array(
					'iframe_host' => array( 'www.scribd.com', 'scribd.com' ),
					'iframe_path' => '#^/embeds/(?P<id>[0-9]+)/content#',
					// The inline injector core pastes after the iframe loads
					// embed_code/inject.js from the same host.
					'script_host' => array( 'www.scribd.com' ),
				),
				'fallback'         => 'https://www.scribd.com/document/{id}',
				'scrub_hint_hosts' => array( 'imgv2-1-f.scribdassets.com', 'imgv2-2-f.scribdassets.com' ),
				'privacy_url'      => 'https://support.scribd.com/hc/en-us/articles/210129366-Privacy-Policy',
				'controller'       => 'Scribd, Inc., San Francisco, USA',
				'note'             => $t( 'Loading this document contacts Scribd, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'           => $t( 'Load document from Scribd' ),
				'strategy'         => 'iframe',
			),
			array(
				'id'           => 'speakerdeck',
				'kind'         => 'document',
				'label'        => 'Speaker Deck',
				'match'        => array(
					'iframe_host' => array( 'speakerdeck.com' ),
					'iframe_path' => '#^/player/[0-9a-f]{32}#',
					'script_host' => array( 'speakerdeck.com' ),
				),
				// The player id is opaque: the player page itself (a working
				// standalone page) serves as the no-JS link.
				'privacy_url'  => 'https://speakerdeck.com/privacy',
				'controller'   => 'Speaker Deck, LLC, USA',
				'note'         => $t( 'Loading this presentation contacts Speaker Deck, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'       => $t( 'Load presentation from Speaker Deck' ),
				'aspect'       => '16:9',
				'iframe_allow' => 'fullscreen',
				'strategy'     => 'iframe',
			),
			array(
				'id'           => 'issuu',
				'kind'         => 'document',
				'label'        => 'Issuu',
				'match'        => array(
					'iframe_host'  => array( 'e.issuu.com' ),
					'iframe_path'  => '#^/embed\\.html$#',
					'iframe_query' => '/(?:^|&)u=(?P<u>[a-z0-9_.-]+)&d=(?P<d>[a-z0-9_.-]+)/i',
				),
				'fallback'     => 'https://issuu.com/{u}/docs/{d}',
				'privacy_url'  => 'https://issuu.com/legal/privacy',
				'controller'   => 'Bending Spoons S.p.A., Milan, Italy',
				'note'         => $t( 'Loading this publication contacts Issuu, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'       => $t( 'Load publication from Issuu' ),
				'iframe_allow' => 'clipboard-write; fullscreen',
				'strategy'     => 'iframe',
			),
			array(
				'id'          => 'kickstarter',
				'label'       => 'Kickstarter',
				'match'       => array(
					'iframe_host' => array( 'www.kickstarter.com', 'kickstarter.com' ),
					'iframe_path' => '#^/projects/(?P<creator>[a-z0-9_-]+)/(?P<slug>[a-z0-9_-]+)/widget/(?:video|card)\\.html#i',
				),
				'fallback'    => 'https://www.kickstarter.com/projects/{creator}/{slug}',
				'privacy_url' => 'https://www.kickstarter.com/privacy',
				'controller'  => 'Kickstarter, PBC, Brooklyn, USA',
				'note'        => $t( 'Loading this project contacts Kickstarter, which receives your IP address and which page you are on, and may set cookies.' ),
				'action'      => $t( 'Load project from Kickstarter' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'          => 'wolfram-cloud',
				'kind'        => 'document',
				'label'       => 'Wolfram Cloud',
				'match'       => array(
					// Two shapes: an iframe on the notebook URL, or (core's
					// default endpoint) the embedder script + an inline embed()
					// call + three stylesheets, all from this host.
					'iframe_host' => array( 'www.wolframcloud.com', 'wolframcloud.com' ),
					'iframe_path' => '#^/obj/#',
					'script_host' => array( 'www.wolframcloud.com' ),
				),
				'privacy_url' => 'https://www.wolfram.com/legal/privacy/wolfram/',
				'controller'  => 'Wolfram Research, Inc., Champaign, USA',
				'note'        => $t( 'Loading this notebook contacts Wolfram, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'      => $t( 'Load notebook from Wolfram Cloud' ),
				'strategy'    => 'iframe',
			),
			array(
				'id'           => 'amazon-kindle',
				'kind'         => 'document',
				'label'        => 'Amazon Kindle',
				'match'        => array(
					'iframe_host' => array( 'read.amazon.com', 'read.amazon.co.uk', 'read.amazon.com.au', 'read.amazon.in', 'read.amazon.cn' ),
					'iframe_path' => '#^/kp/(?:card|embed)#',
				),
				// The preview card is a standalone page and the marketplace
				// differs per host: the card URL itself is the no-JS link.
				'privacy_url'  => 'https://www.amazon.com/privacy',
				'controller'   => 'Amazon.com, Inc., Seattle, USA',
				'note'         => $t( 'Loading this preview contacts Amazon, which receives your IP address and which page you are on, and sets cookies.' ),
				'action'       => $t( 'Load preview from Amazon Kindle' ),
				'iframe_allow' => 'clipboard-write; fullscreen',
				'strategy'     => 'iframe',
			),
		);
	}
}
