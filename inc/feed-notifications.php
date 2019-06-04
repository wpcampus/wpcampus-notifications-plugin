<?php
/**
 * RSS2 Feed Template for displaying our tweets.
 */

header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?>';

// Get the feed platform.
global $wp_query;
$feed_platform = wpcampus_notifications()->get_query_feed_platform( $wp_query );

?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/">
	<channel>
		<title><?php wp_title_rss(); ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php bloginfo_rss( 'url' ) ?></link>
		<description><?php bloginfo_rss( 'description' ) ?></description>
		<lastBuildDate><?php
			$date = get_lastpostmodified( 'GMT' );
			echo $date ? mysql2date( 'r', $date, false ) : date( 'r' );
			?></lastBuildDate>
		<language><?php bloginfo_rss( 'language' ); ?></language>
		<sy:updatePeriod>hourly</sy:updatePeriod>
		<sy:updateFrequency>1</sy:updateFrequency>
		<?php

		while ( have_posts() ) : the_post();

			$content = wpcampus_notifications()->get_notification_message( get_the_ID(), $feed_platform );

			?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss() ?></link>
				<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
				<dc:creator><![CDATA[<?php the_author() ?>]]></dc:creator>
				<?php the_category_rss( 'rss2' ) ?>
				<guid isPermaLink="false"><?php the_guid(); ?></guid>
				<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
			</item>
			<?php
		endwhile;

		?>
	</channel>
</rss>
