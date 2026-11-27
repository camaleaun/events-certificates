<?php
/**
 * Step 1 of the Meetup tutorial: the organizer's list of past events.
 *
 * Block markup only: every word comes escaped from $labels and every picture, date and count from $scene.
 *
 * @package EventsCertificate
 *
 * @var array<string, string> $labels Meetup's words, in the current language.
 * @var array<string, mixed>  $scene  The scenario's data.
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"49.1px","bottom":"0px"},"margin":{"bottom":"22.9px"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group" style="margin-bottom:22.9px;padding-top:49.1px;padding-bottom:0px">
		<!-- wp:group {"style":{"spacing":{"blockGap":"8px"},"css":"align-items:end"},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group has-custom-css">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"700","fontSize":"24px","lineHeight":"1.25"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}}}} -->
			<p class="has-text-color has-link-color" style="color:#232326;font-size:24px;font-style:normal;font-weight:700;line-height:1.25">
				<?php echo esc_html( $labels['past_events'] ); ?>
			</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontStyle":"normal","fontWeight":"500","fontSize":"16px","lineHeight":"1.5","letterSpacing":"0.02em"},"color":{"text":"#69696c"}}} -->
			<p class="has-text-color has-link-color" style="color:#69696c;font-size:16px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.5">
				<?php echo (int) $scene['total']; ?>
			</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"style":{"color":{"text":"#7661d2"},"elements":{"link":{"color":{"text":"#7661d2"}}},"typography":{"fontSize":"16px","fontStyle":"normal","fontWeight":"600","lineHeight":"1.375","letterSpacing":"0.02em"},"spacing":{"padding":{"right":"2px","left":"2px"}}}} -->
		<p class="has-text-color has-link-color" style="color:#7661d2;padding-right:2px;padding-left:2px;font-size:16px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.375">
			<?php echo esc_html( $labels['see_all'] ); ?>
		</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"style":{"spacing":{"blockGap":{"top":"22.9px","left":"28px"}}},"layout":{"type":"grid","columnCount":2}} -->
	<div class="wp-block-group">
		<!-- wp:group {"style":{"layout":{"columnSpan":1,"rowSpan":1},"spacing":{"blockGap":"0"},"dimensions":{"minWidth":"100%"},"css":"position:relative"},"layout":{"type":"default"}} -->
		<div class="wp-block-group has-custom-css" style="min-width:100%">
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"},"css":"overflow:hidden;","dimensions":{"minWidth":"100%"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group has-custom-css" style="min-width:100%">
				<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">
					<!-- wp:group {"style":{"css":"position:absolute;bottom:8px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
					<div class="wp-block-group has-custom-css">
						<!-- wp:group {"style":{"spacing":{"blockGap":"6px","padding":{"top":"8px","bottom":"8px","left":"16px","right":"16px"}},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"text":"#000000","background":"#ffffff"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
						<div class="wp-block-group has-text-color has-background has-link-color" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;padding-top:8px;padding-right:16px;padding-bottom:8px;padding-left:16px;color:#000000;background-color:#ffffff;">
							<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","lineHeight":"1.33333333","fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em"}}} -->
							<p style="font-size:12px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333">
								<?php echo esc_html( $labels['manage'] ); ?>
							</p>
							<!-- /wp:paragraph -->

							<!-- wp:icon {"icon":"events-certificates\/chevron-down","style":{"dimensions":{"width":"12px"}}} /-->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->

					<!-- wp:group {"style":{"css":"position:absolute;top:12px;left:12px"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"}} -->
					<div class="wp-block-group has-custom-css">
						<!-- wp:group {"style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"},"blockGap":"4px"},"border":{"radius":{"topLeft":"12px","topRight":"12px","bottomLeft":"12px","bottomRight":"12px"}},"css":"position:absolute;top:0;left:0;z-index:1","color":{"background":"#ffffff"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
						<div class="wp-block-group has-custom-css has-background" style="border-top-left-radius:12px;border-top-right-radius:12px;border-bottom-left-radius:12px;border-bottom-right-radius:12px;padding-top:4px;padding-right:8px;padding-bottom:4px;padding-left:8px;background-color:#ffffff;">
							<!-- wp:icon {"icon":"events-certificates\/check","style":{"elements":{"link":{"color":{"text":"#0a884a"}}},"dimensions":{"width":"12px"},"color":{"text":"#0a884a"}}} /-->

							<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontStyle":"normal","fontWeight":"600","lineHeight":"1.33333333","letterSpacing":"0.02em"},"spacing":{"padding":{"right":"2px","left":"2px"}},"color":{"text":"#232326"}}} -->
							<p class="has-text-color has-link-color" style="padding-right:2px;padding-left:2px;font-size:12px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333;color:#232326;">
								<?php echo esc_html( $labels['went'] ); ?>
							</p>
							<!-- /wp:paragraph -->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->

					<!-- wp:image {"aspectRatio":"127/68","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} -->
					<figure class="wp-block-image size-full has-custom-border">
						<img src="<?php echo esc_url( $scene['events'][0]['photo'] ); ?>" alt="" style="border-top-left-radius:24px;border-top-right-radius:24px;border-bottom-left-radius:24px;border-bottom-right-radius:24px;aspect-ratio:127/68;object-fit:cover;object-position:50% 0"/>
					</figure>
					<!-- /wp:image -->

					<!-- wp:group {"style":{"layout":{"selfStretch":"fit","flexSize":null},"css":"position:absolute;top:8.75px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
					<div class="wp-block-group has-custom-css">
						<!-- wp:group {"style":{"css":"backdrop-filter:blur(4px);position:absolute;top:0;right:0","color":{"background":"#12121566"},"spacing":{"padding":{"top":"6px","bottom":"6px","left":"6px","right":"6px"}},"border":{"radius":{"topLeft":"50%","topRight":"50%","bottomLeft":"50%","bottomRight":"50%"}}},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group has-custom-css has-background" style="border-top-left-radius:50%;border-top-right-radius:50%;border-bottom-left-radius:50%;border-bottom-right-radius:50%;background-color:#12121566;padding-top:6px;padding-right:6px;padding-bottom:6px;padding-left:6px">
							<!-- wp:icon {"icon":"events-certificates\/heart","style":{"dimensions":{"width":"20px"},"color":{"text":"#ffffff"}}} /-->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"padding":{"top":"19.9px"},"blockGap":"0"}},"layout":{"type":"constrained"}} -->
				<div class="wp-block-group" style="padding-top:19.9px">
					<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em","fontSize":"18px","lineHeight":"1.33333333"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}},"css":"text-wrap-style:balance"}} -->
					<p class="has-custom-css has-text-color has-link-color" style="color:#232326;font-size:18px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333">
						<?php echo esc_html( $scene['events'][0]['title'] ); ?>
					</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"style":{"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em"},"spacing":{"margin":{"top":"4px"}}}} -->
					<p class="has-text-color has-link-color" style="color:#69696c;margin-top:4px;font-size:14px;letter-spacing:0.02em;line-height:1.42857143">
						<?php echo esc_html( $scene['events'][0]['when'] ); ?>
					</p>
					<!-- /wp:paragraph -->

					<!-- wp:group {"style":{"spacing":{"margin":{"top":"2px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group" style="margin-top:2px">
						<!-- wp:paragraph {"style":{"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em"},"css":"text-overflow:ellipsis;\noverflow:hidden;\nwhite-space:nowrap;\nmax-width:95%;"}} -->
						<p class="has-custom-css has-text-color has-link-color" style="color:#69696c;font-size:14px;letter-spacing:0.02em;line-height:1.42857143">
							<?php echo esc_html( $scene['events'][0]['place'] ); ?>
						</p>
						<!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->

					<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group">
						<!-- wp:group {"style":{"spacing":{"blockGap":"0","margin":{"top":"8px","bottom":"8px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
						<div class="wp-block-group" style="margin-top:8px;margin-bottom:8px">
							<!-- wp:image {"width":"18px","height":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"css":"border-radius:999px;overflow:hidden;mask:radial-gradient(circle at 110% 50%,transparent 10px,black 8px);"}} -->
							<figure class="wp-block-image size-full is-resized has-custom-css">
								<img src="<?php echo esc_url( $scene['events'][0]['avatars'][0] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:18px"/>
							</figure>
							<!-- /wp:image -->

							<!-- wp:image {"width":"18px","height":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"spacing":{"margin":{"left":"-6px"}},"css":"border-radius:999px;overflow:hidden;mask:radial-gradient(circle at 110% 50%,transparent 10px,black 8px);"}} -->
							<figure class="wp-block-image size-full is-resized has-custom-css" style="margin-left:-6px">
								<img src="<?php echo esc_url( $scene['events'][0]['avatars'][1] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:18px"/>
							</figure>
							<!-- /wp:image -->

							<!-- wp:image {"width":"18px","height":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"spacing":{"margin":{"left":"-6px"}},"css":"border-radius:999px;overflow:hidden;"}} -->
							<figure class="wp-block-image size-full is-resized" style="margin-left:-6px">
								<img src="<?php echo esc_url( $scene['events'][0]['avatars'][2] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:18px"/>
							</figure>
							<!-- /wp:image -->
						</div>
						<!-- /wp:group -->

						<!-- wp:paragraph {"style":{"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}},"typography":{"fontStyle":"normal","fontWeight":"600","fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em"},"spacing":{"padding":{"top":"8px","bottom":"8px"}}}} -->
						<p class="has-text-color has-link-color" style="color:#232326;padding-top:8px;padding-bottom:8px;font-size:14px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.42857143">
							<?php echo esc_html( $scene['events'][0]['attendees'] ); ?>
						</p>
						<!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"evcert-tsi-frame","style":{"css":"position:absolute;top:-40.9px;right:-5.3px;bottom:4.3px;left:-28.4px;border:7.1px solid #e93421;border-radius:10px;box-sizing:border-box;pointer-events:none;z-index:3"},"layout":{"type":"default"}} -->
			<div class="wp-block-group evcert-tsi-frame has-custom-css"></div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"layout":{"columnSpan":1,"rowSpan":1},"spacing":{"blockGap":"0"},"css":"overflow:hidden;","dimensions":{"minWidth":"100%"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-custom-css" style="min-width:100%">
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"css":"position:absolute;bottom:8px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"spacing":{"blockGap":"6px","padding":{"top":"8px","bottom":"8px","left":"16px","right":"16px"}},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"text":"#000000","background":"#ffffff"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-text-color has-background has-link-color" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;padding-top:8px;padding-right:16px;padding-bottom:8px;padding-left:16px;color:#000000;background-color:#ffffff;">
						<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","lineHeight":"1.33333333","fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em"}}} -->
						<p style="font-size:12px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333">
							<?php echo esc_html( $labels['manage'] ); ?>
						</p>
						<!-- /wp:paragraph -->

						<!-- wp:icon {"icon":"events-certificates\/chevron-down","style":{"dimensions":{"width":"12px"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:image {"aspectRatio":"127/68","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} -->
				<figure class="wp-block-image size-full has-custom-border">
					<img src="<?php echo esc_url( $scene['events'][1]['photo'] ); ?>" alt="" style="border-top-left-radius:24px;border-top-right-radius:24px;border-bottom-left-radius:24px;border-bottom-right-radius:24px;aspect-ratio:127/68;object-fit:cover;object-position:50% 0"/>
				</figure>
				<!-- /wp:image -->

				<!-- wp:group {"style":{"layout":{"selfStretch":"fit","flexSize":null},"css":"position:absolute;top:8.75px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"css":"backdrop-filter:blur(4px);position:absolute;top:0;right:0","color":{"background":"#12121566"},"spacing":{"padding":{"top":"6px","bottom":"6px","left":"6px","right":"6px"}},"border":{"radius":{"topLeft":"50%","topRight":"50%","bottomLeft":"50%","bottomRight":"50%"}}},"layout":{"type":"constrained"}} -->
					<div class="wp-block-group has-custom-css has-background" style="border-top-left-radius:50%;border-top-right-radius:50%;border-bottom-left-radius:50%;border-bottom-right-radius:50%;background-color:#12121566;padding-top:6px;padding-right:6px;padding-bottom:6px;padding-left:6px">
						<!-- wp:icon {"icon":"events-certificates\/heart","style":{"dimensions":{"width":"20px"},"color":{"text":"#ffffff"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"style":{"spacing":{"padding":{"top":"19.9px"},"blockGap":"0"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group" style="padding-top:19.9px">
				<!-- wp:paragraph {"style":{"typography":{"fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em","fontSize":"18px","lineHeight":"1.33333333"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}},"css":"text-wrap-style:balance"}} -->
				<p class="has-custom-css has-text-color has-link-color" style="color:#232326;font-size:18px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333">
					<?php echo esc_html( $scene['events'][1]['title'] ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:paragraph {"style":{"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em"},"spacing":{"margin":{"top":"4px"}}}} -->
				<p class="has-text-color has-link-color" style="color:#69696c;margin-top:4px;font-size:14px;letter-spacing:0.02em;line-height:1.42857143">
					<?php echo esc_html( $scene['events'][1]['when'] ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:group {"style":{"spacing":{"margin":{"top":"2px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group" style="margin-top:2px">
					<!-- wp:paragraph {"style":{"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em"},"css":"text-overflow:ellipsis;overflow:hidden;white-space:nowrap;max-width:95%"}} -->
					<p class="has-custom-css has-text-color has-link-color" style="color:#69696c;font-size:14px;letter-spacing:0.02em;line-height:1.42857143">
						<?php echo esc_html( $scene['events'][1]['place'] ); ?>
					</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group">
					<!-- wp:group {"style":{"spacing":{"blockGap":"0","margin":{"top":"8px","bottom":"8px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group" style="margin-top:8px;margin-bottom:8px">
						<!-- wp:image {"width":"18px","height":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"css":"border-radius:999px;overflow:hidden;mask:radial-gradient(circle at 110% 50%,transparent 10px,black 8px);"}} -->
						<figure class="wp-block-image size-full is-resized has-custom-css">
							<img src="<?php echo esc_url( $scene['events'][1]['avatars'][0] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:18px"/>
						</figure>
						<!-- /wp:image -->

						<!-- wp:image {"width":"18px","height":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"spacing":{"margin":{"left":"-6px"}},"css":"border-radius:999px;overflow:hidden;mask:radial-gradient(circle at 110% 50%,transparent 10px,black 8px);"}} -->
						<figure class="wp-block-image size-full is-resized has-custom-css" style="margin-left:-6px">
							<img src="<?php echo esc_url( $scene['events'][1]['avatars'][1] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:18px"/>
						</figure>
						<!-- /wp:image -->

						<!-- wp:image {"width":"18px","height":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"spacing":{"margin":{"left":"-6px"}},"css":"border-radius:999px;overflow:hidden;"}} -->
						<figure class="wp-block-image size-full is-resized" style="margin-left:-6px">
							<img src="<?php echo esc_url( $scene['events'][1]['avatars'][2] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:18px"/>
						</figure>
						<!-- /wp:image -->
					</div>
					<!-- /wp:group -->

					<!-- wp:paragraph {"style":{"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}},"typography":{"fontStyle":"normal","fontWeight":"600","fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em"},"spacing":{"padding":{"top":"8px","bottom":"8px"}}}} -->
					<p class="has-text-color has-link-color" style="color:#232326;padding-top:8px;padding-bottom:8px;font-size:14px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.42857143">
						<?php echo esc_html( $scene['events'][1]['attendees'] ); ?>
					</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"layout":{"columnSpan":1,"rowSpan":1},"spacing":{"blockGap":"0"},"css":"overflow:hidden;","dimensions":{"minWidth":"100%"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-custom-css" style="min-width:100%">
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"css":"position:absolute;bottom:8px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"spacing":{"blockGap":"6px","padding":{"top":"8px","bottom":"8px","left":"16px","right":"16px"}},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"text":"#000000","background":"#ffffff"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-text-color has-background has-link-color" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;padding-top:8px;padding-right:16px;padding-bottom:8px;padding-left:16px;color:#000000;background-color:#ffffff;">
						<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","lineHeight":"1.33333333","fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em"}}} -->
						<p style="font-size:12px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333">
							<?php echo esc_html( $labels['manage'] ); ?>
						</p>
						<!-- /wp:paragraph -->

						<!-- wp:icon {"icon":"events-certificates\/chevron-down","style":{"dimensions":{"width":"12px"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"css":"position:absolute;top:12px;left:12px"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"spacing":{"padding":{"top":"4px","bottom":"4px","left":"8px","right":"8px"},"blockGap":"4px"},"border":{"radius":{"topLeft":"12px","topRight":"12px","bottomLeft":"12px","bottomRight":"12px"}},"css":"position:absolute;top:0;left:0;z-index:1","color":{"background":"#ffffff"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-custom-css has-background" style="border-top-left-radius:12px;border-top-right-radius:12px;border-bottom-left-radius:12px;border-bottom-right-radius:12px;padding-top:4px;padding-right:8px;padding-bottom:4px;padding-left:8px;background-color:#ffffff;">
						<!-- wp:icon {"icon":"events-certificates\/check","style":{"elements":{"link":{"color":{"text":"#0a884a"}}},"dimensions":{"width":"12px"},"color":{"text":"#0a884a"}}} /-->

						<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontStyle":"normal","fontWeight":"600","lineHeight":"1.33333333","letterSpacing":"0.02em"},"spacing":{"padding":{"right":"2px","left":"2px"}},"color":{"text":"#232326"}}} -->
						<p class="has-text-color has-link-color" style="padding-right:2px;padding-left:2px;font-size:12px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333;color:#232326;">
							<?php echo esc_html( $labels['went'] ); ?>
						</p>
						<!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:image {"aspectRatio":"127/68","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} -->
				<figure class="wp-block-image size-full has-custom-border">
					<img src="<?php echo esc_url( $scene['events'][2]['photo'] ); ?>" alt="" style="border-top-left-radius:24px;border-top-right-radius:24px;border-bottom-left-radius:24px;border-bottom-right-radius:24px;aspect-ratio:127/68;object-fit:cover;object-position:50% 0"/>
				</figure>
				<!-- /wp:image -->

				<!-- wp:group {"style":{"layout":{"selfStretch":"fit","flexSize":null},"css":"position:absolute;top:8.75px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"css":"backdrop-filter:blur(4px);position:absolute;top:0;right:0","color":{"background":"#12121566"},"spacing":{"padding":{"top":"6px","bottom":"6px","left":"6px","right":"6px"}},"border":{"radius":{"topLeft":"50%","topRight":"50%","bottomLeft":"50%","bottomRight":"50%"}}},"layout":{"type":"constrained"}} -->
					<div class="wp-block-group has-custom-css has-background" style="border-top-left-radius:50%;border-top-right-radius:50%;border-bottom-left-radius:50%;border-bottom-right-radius:50%;background-color:#12121566;padding-top:6px;padding-right:6px;padding-bottom:6px;padding-left:6px">
						<!-- wp:icon {"icon":"events-certificates\/heart","style":{"dimensions":{"width":"20px"},"color":{"text":"#ffffff"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"style":{"layout":{"columnSpan":1,"rowSpan":1},"spacing":{"blockGap":"0"},"css":"overflow:hidden;","dimensions":{"minWidth":"100%"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group has-custom-css" style="min-width:100%">
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"css":"position:absolute;bottom:8px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"spacing":{"blockGap":"6px","padding":{"top":"8px","bottom":"8px","left":"16px","right":"16px"}},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"text":"#000000","background":"#ffffff"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-text-color has-background has-link-color" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;padding-top:8px;padding-right:16px;padding-bottom:8px;padding-left:16px;color:#000000;background-color:#ffffff;">
						<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","lineHeight":"1.33333333","fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em"}}} -->
						<p style="font-size:12px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333">
							<?php echo esc_html( $labels['manage'] ); ?>
						</p>
						<!-- /wp:paragraph -->

						<!-- wp:icon {"icon":"events-certificates\/chevron-down","style":{"dimensions":{"width":"12px"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:image {"aspectRatio":"127/68","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} -->
				<figure class="wp-block-image size-full has-custom-border">
					<img src="<?php echo esc_url( $scene['events'][3]['photo'] ); ?>" alt="" style="border-top-left-radius:24px;border-top-right-radius:24px;border-bottom-left-radius:24px;border-bottom-right-radius:24px;aspect-ratio:127/68;object-fit:cover;object-position:50% 0"/>
				</figure>
				<!-- /wp:image -->

				<!-- wp:group {"style":{"layout":{"selfStretch":"fit","flexSize":null},"css":"position:absolute;top:8.75px;right:8px;z-index:1"},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"css":"backdrop-filter:blur(4px);position:absolute;top:0;right:0","color":{"background":"#12121566"},"spacing":{"padding":{"top":"6px","bottom":"6px","left":"6px","right":"6px"}},"border":{"radius":{"topLeft":"50%","topRight":"50%","bottomLeft":"50%","bottomRight":"50%"}}},"layout":{"type":"constrained"}} -->
					<div class="wp-block-group has-custom-css has-background" style="border-top-left-radius:50%;border-top-right-radius:50%;border-bottom-left-radius:50%;border-bottom-right-radius:50%;background-color:#12121566;padding-top:6px;padding-right:6px;padding-bottom:6px;padding-left:6px">
						<!-- wp:icon {"icon":"events-certificates\/heart","style":{"dimensions":{"width":"20px"},"color":{"text":"#ffffff"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
