<?php
/**
 * Step 3 of the Meetup tutorial: the organizer's list of people, with the Manage button.
 *
 * Block markup only: every word comes escaped from $labels and every picture, count and name from $scene.
 *
 * @package EventsCertificate
 *
 * @var array<string, string> $labels Meetup's words, in the current language.
 * @var array<string, mixed>  $scene  The scenario's data.
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"style":{"spacing":{"blockGap":"0"},"css":"width:680px"},"layout":{"type":"default"}} -->
<div class="wp-block-group has-custom-css">
	<!-- wp:group {"style":{"spacing":{"blockGap":"4px"},"dimensions":{"minHeight":"24.5px"},"color":{"text":"#7d7d82"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
	<div class="wp-block-group has-text-color" style="color:#7d7d82;min-height:24.5px">
		<!-- wp:group {"style":{"spacing":{"blockGap":"4px"},"dimensions":{"minHeight":"20px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group" style="min-height:20px">
			<!-- wp:icon {"icon":"events-certificates\/arrow-left","style":{"dimensions":{"width":"14px"}}} /-->
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}}}} -->
			<p style="padding-right:2px;padding-left:2px;font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $labels['back'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"margin":{"top":"12px"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
	<div class="wp-block-group" style="margin-top:12px">
		<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"40px","fontWeight":"700","lineHeight":"1.2","letterSpacing":"-0.02em"},"color":{"text":"#232326"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
		<h1 class="wp-block-heading has-text-color" style="color:#232326;margin-top:0;margin-bottom:0;font-size:40px;font-weight:700;letter-spacing:-0.02em;line-height:1.2"><?php echo esc_html( $labels['attendees_en'] ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:group {"style":{"spacing":{"blockGap":"6px","margin":{"top":"10.5px","right":"-12px"},"padding":{"top":"6px","right":"12px","bottom":"6px","left":"12px"}},"border":{"radius":"999px"},"color":{"text":"#634fb8","background":"#1212150c"},"css":"position:relative"},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-text-color has-background has-custom-css" style="border-radius:999px;color:#634fb8;background-color:#1212150c;margin-top:10.5px;margin-right:-12px;padding-top:6px;padding-right:12px;padding-bottom:6px;padding-left:12px">
			<!-- wp:icon {"icon":"events-certificates\/gear","style":{"dimensions":{"width":"16px"}}} /-->
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.375","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}}}} -->
			<p style="padding-right:2px;padding-left:2px;font-size:16px;font-weight:600;letter-spacing:0.02em;line-height:1.375"><?php echo esc_html( $labels['manage_en'] ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:group {"className":"evcert-tsi-frame","style":{"css":"position:absolute;top:-18.3px;right:-32.7px;bottom:-29.6px;left:-31.3px;border:7.3px solid #e93421;border-radius:10px;box-sizing:border-box;pointer-events:none"},"layout":{"type":"default"}} -->
			<div class="wp-block-group evcert-tsi-frame has-custom-css"></div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.5","letterSpacing":"0.02em"},"color":{"text":"#69696c"},"spacing":{"margin":{"top":"10px","bottom":"0"}}}} -->
	<p class="has-text-color" style="color:#69696c;margin-top:10px;margin-bottom:0;font-size:16px;letter-spacing:0.02em;line-height:1.5"><?php echo esc_html( $scene['events'][0]['title'] ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"28px","margin":{"top":"56px"}}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group" style="margin-top:56px">
		<!-- wp:group {"style":{"spacing":{"padding":{"top":"12px","bottom":"12px","left":"15px","right":"16px"},"blockGap":"9px"},"border":{"color":"#e7e7ea","width":"1px","radius":"999px"},"color":{"background":"#ffffff","text":"#7d7d82"},"css":"width:628px;box-sizing:border-box;min-height:50px"},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-border-color has-text-color has-background has-custom-css" style="border-color:#e7e7ea;border-width:1px;border-radius:999px;color:#7d7d82;background-color:#ffffff;padding-top:12px;padding-right:16px;padding-bottom:12px;padding-left:15px">
			<!-- wp:icon {"icon":"events-certificates\/search","style":{"dimensions":{"width":"20px"}}} /-->
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.5","letterSpacing":"0.02em"}}} -->
			<p style="font-size:16px;letter-spacing:0.02em;line-height:1.5"><?php echo esc_html( $labels['search_people'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:icon {"icon":"events-certificates\/sort","style":{"dimensions":{"width":"24px"},"color":{"text":"#353538"}}} /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"8px","margin":{"top":"24px"},"padding":{"left":"8px"}}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
	<div class="wp-block-group" style="margin-top:24px;padding-left:8px">
		<!-- wp:group {"style":{"spacing":{"padding":{"top":"8px","bottom":"8px","left":"12px","right":"12px"},"blockGap":"4px"},"border":{"radius":"999px"},"color":{"text":"#ffffff","background":"#7661d2"},"dimensions":{"minHeight":"36px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-text-color has-background" style="border-radius:999px;color:#ffffff;background-color:#7661d2;min-height:36px;padding-top:8px;padding-right:12px;padding-bottom:8px;padding-left:12px">
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}},"css":"white-space:nowrap"}} -->
			<p class="has-custom-css" style="padding-right:2px;padding-left:2px;font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $scene['pills']['attendees'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"spacing":{"padding":{"top":"8px","bottom":"8px","left":"12px","right":"12px"},"blockGap":"4px"},"border":{"radius":"999px"},"color":{"text":"#232326"},"dimensions":{"minHeight":"36px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-text-color" style="border-radius:999px;color:#232326;min-height:36px;padding-top:8px;padding-right:12px;padding-bottom:8px;padding-left:12px">
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}},"css":"white-space:nowrap"}} -->
			<p class="has-custom-css" style="padding-right:2px;padding-left:2px;font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $scene['pills']['checked'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"spacing":{"padding":{"top":"8px","bottom":"8px","left":"12px","right":"12px"},"blockGap":"4px"},"border":{"radius":"999px"},"color":{"text":"#232326"},"dimensions":{"minHeight":"36px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-text-color" style="border-radius:999px;color:#232326;min-height:36px;padding-top:8px;padding-right:12px;padding-bottom:8px;padding-left:12px">
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}},"css":"white-space:nowrap"}} -->
			<p class="has-custom-css" style="padding-right:2px;padding-left:2px;font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $scene['pills']['not_checked'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"spacing":{"padding":{"top":"8px","bottom":"8px","left":"12px","right":"12px"},"blockGap":"4px"},"border":{"radius":"999px"},"color":{"text":"#232326"},"dimensions":{"minHeight":"36px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-text-color" style="border-radius:999px;color:#232326;min-height:36px;padding-top:8px;padding-right:12px;padding-bottom:8px;padding-left:12px">
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}},"css":"white-space:nowrap"}} -->
			<p class="has-custom-css" style="padding-right:2px;padding-left:2px;font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $scene['pills']['absent'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"spacing":{"padding":{"top":"8px","bottom":"8px","left":"12px","right":"12px"},"blockGap":"4px"},"border":{"radius":"999px"},"color":{"text":"#232326"},"dimensions":{"minHeight":"36px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group has-text-color" style="border-radius:999px;color:#232326;min-height:36px;padding-top:8px;padding-right:12px;padding-bottom:8px;padding-left:12px">
			<!-- wp:icon {"icon":"events-certificates\/x","style":{"dimensions":{"width":"16px"}}} /-->
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"},"spacing":{"padding":{"left":"2px","right":"2px"}},"css":"white-space:nowrap"}} -->
			<p class="has-custom-css" style="padding-right:2px;padding-left:2px;font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $scene['pills']['canceled'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"0","margin":{"top":"48px"}},"border":{"radius":"24px"},"color":{"background":"#d3daff"},"background":{"backgroundImage":{"url":"<?php echo esc_url( $scene['ui']['banner-bg'] ); ?>","id":0,"source":"file"},"backgroundSize":"cover","backgroundPosition":"50% 50%"},"dimensions":{"minHeight":"98.81px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
	<div class="wp-block-group has-background" style="border-radius:24px;background-color:#d3daff;background-image:url(<?php echo esc_url( $scene['ui']['banner-bg'] ); ?>);background-position:50% 50%;background-size:cover;min-height:98.81px;margin-top:48px">
		<!-- wp:image {"width":"132px","height":"98.81px","scale":"cover","sizeSlug":"full","linkDestination":"none"} -->
		<figure class="wp-block-image size-full is-resized">
			<img src="<?php echo esc_url( $scene['ui']['banner-people'] ); ?>" alt="" style="object-fit:cover;width:132px;height:98.81px"/>
		</figure>
		<!-- /wp:image -->

		<!-- wp:group {"style":{"spacing":{"padding":{"top":"16px","bottom":"16px","left":"24px","right":"12px"}},"css":"flex:1 1 0;min-width:0;box-sizing:border-box"},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-custom-css" style="padding-top:16px;padding-right:12px;padding-bottom:16px;padding-left:24px">
			<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.375","fontWeight":"600","letterSpacing":"0.02em"},"color":{"text":"#232326"}}} -->
			<p class="has-text-color" style="color:#232326;font-size:16px;font-weight:600;letter-spacing:0.02em;line-height:1.375"><?php echo esc_html( $labels['promo_text'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"spacing":{"padding":{"left":"18px","right":"24px"}},"css":"flex:none;box-sizing:border-box"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
		<div class="wp-block-group has-custom-css" style="padding-right:24px;padding-left:18px">
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"10px","bottom":"10px","left":"20px","right":"20px"}},"border":{"radius":"999px"},"color":{"gradient":"linear-gradient(#232326 0px,#353538)","text":"#ffffff"},"dimensions":{"minHeight":"40px"},"css":"box-sizing:border-box;box-shadow:-5px -5px 5px 0 rgba(0,0,16,0.2) inset,5px -5px 5px 0 rgba(0,0,16,0.2) inset,0 3px 4px 0 rgba(0,0,16,0.2) inset,0 4px 5px 0 rgba(255,255,255,0.23) inset"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","verticalAlignment":"center"}} -->
			<div class="wp-block-group has-text-color has-background has-custom-css" style="border-radius:999px;color:#ffffff;background:linear-gradient(#232326 0px,#353538);min-height:40px;padding-top:10px;padding-right:20px;padding-bottom:10px;padding-left:20px">
				<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","fontWeight":"600","letterSpacing":"0.02em"}}} -->
				<p style="font-size:14px;font-weight:600;letter-spacing:0.02em;line-height:1.42857143"><?php echo esc_html( $labels['promo_button'] ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
