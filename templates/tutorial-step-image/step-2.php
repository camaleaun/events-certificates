<?php
/**
 * Step 2 of the Meetup tutorial: a meetup's own page, with the organizer's buttons.
 *
 * Block markup only: every word comes escaped from $labels and every picture, name, date and count from $scene.
 *
 * @package EventsCertificate
 *
 * @var array<string, string> $labels Meetup's words, in the current language.
 * @var array<string, mixed>  $scene  The scenario's data.
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"left":"0px","right":"0px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-right:0px;padding-left:0px">
	<!-- wp:columns {"isStackedOnMobile":false,"align":"wide","style":{"spacing":{"blockGap":{"top":"24px","left":"32px"}},"css":"justify-content:space-between"}} -->
	<div class="wp-block-columns alignwide is-not-stacked-on-mobile has-custom-css">
		<!-- wp:column {"width":"","style":{"css":"max-width:620px","spacing":{"padding":{"top":"40px"},"blockGap":"60px"}}} -->
		<div class="wp-block-column has-custom-css" style="padding-top:40px">
			<!-- wp:group {"style":{"spacing":{"blockGap":"24px"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"style":{"typography":{"fontSize":"48px","fontStyle":"normal","fontWeight":"700","letterSpacing":"-0.02em","lineHeight":"1.16666667"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}},"css":"text-wrap:balance"}} -->
				<p class="has-custom-css has-text-color has-link-color" style="color:#232326;font-size:48px;font-style:normal;font-weight:700;letter-spacing:-0.02em;line-height:1.16666667">
					<?php echo esc_html( $scene['events'][0]['title'] ); ?>
				</p>
				<!-- /wp:paragraph -->

				<!-- wp:group {"style":{"spacing":{"blockGap":"16px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group">
					<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
					<div class="wp-block-group">
						<?php $evcert_mask = ! empty( $scene['host']['super'] ) ? 'mask:radial-gradient(circle, transparent 20px,black 20px),radial-gradient(circle at 37px 33px,transparent 11px,black 11px);' : ''; ?>
						<!-- wp:image {"width":"40px","height":"40px","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"40px"},"css":"<?php echo esc_attr( $evcert_mask ); ?>overflow:hidden;outline-color:#1212150c;outline-style:solid;outline-width:1px;outline-offset:-1px;border-radius:50%"}} -->
						<figure class="wp-block-image size-full is-resized has-custom-css">
							<img src="<?php echo esc_url( $scene['host']['photo'] ); ?>" alt="" style="object-fit:cover;width:40px;height:40px"/>
						</figure>
						<!-- /wp:image -->

						<?php if ( ! empty( $scene['host']['super'] ) ) : ?>
						<!-- wp:image {"width":"18px","aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"css":"position:absolute;bottom:-2px;right:-6px"}} -->
						<figure class="wp-block-image size-full is-resized has-custom-css">
							<img src="<?php echo esc_url( $scene['ui']['super-organizer'] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover;width:18px;height:auto"/>
						</figure>
						<!-- /wp:image -->
						<?php endif; ?>
					</div>
					<!-- /wp:group -->

					<!-- wp:group {"style":{"spacing":{"blockGap":"0"},"css":"\u0026 strong{font-weight:600}"},"layout":{"type":"flex","orientation":"vertical"}} -->
					<div class="wp-block-group has-custom-css">
						<!-- wp:paragraph {"style":{"color":{"text":"#232326"},"typography":{"fontSize":"18px","lineHeight":"1.55555556","letterSpacing":"0.02em"}}} -->
						<p class="has-text-color" style="color:#232326;font-size:18px;letter-spacing:0.02em;line-height:1.55555556">
							<?php echo wp_kses_post( $scene['host']['line'] ); ?>
						</p>
						<!-- /wp:paragraph -->

						<?php if ( ! empty( $scene['host']['super'] ) ) : ?>
						<!-- wp:paragraph {"style":{"color":{"text":"#232326"},"typography":{"fontSize":"18px","lineHeight":"1.55555556","letterSpacing":"0.02em"}}} -->
						<p class="has-text-color" style="color:#232326;font-size:18px;letter-spacing:0.02em;line-height:1.55555556">
							<?php echo wp_kses_post( $scene['host']['organizer'] ); ?>
						</p>
						<!-- /wp:paragraph -->
						<?php endif; ?>
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
			<div class="wp-block-group">
				<!-- wp:image {"id":1594,"aspectRatio":"16/9","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"108px"},"border":{"radius":{"topLeft":"12px","topRight":"12px","bottomLeft":"12px","bottomRight":"12px"}}}} -->
				<figure class="wp-block-image size-full has-custom-border">
					<img src="<?php echo esc_url( $scene['hero'] ); ?>" alt="" style="border-top-left-radius:12px;border-top-right-radius:12px;border-bottom-left-radius:12px;border-bottom-right-radius:12px;aspect-ratio:16/9;object-fit:cover"/>
				</figure>
				<!-- /wp:image -->

				<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
				<div class="wp-block-group">
					<!-- wp:group {"style":{"spacing":{"blockGap":"2px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group">
						<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","fontStyle":"normal","fontWeight":"600","letterSpacing":"0.02em","lineHeight":"1.33333333333333"},"css":"text-wrap:balance"}} -->
						<p class="has-custom-css" style="font-size:18px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.33333333333333">
							<?php echo esc_html( $scene['group']['name'] ); ?>
						</p>
						<!-- /wp:paragraph -->

						<!-- wp:icon {"icon":"core\/chevron-right","style":{"dimensions":{"width":"20px"},"color":{"text":"#7d7d82"},"elements":{"link":{"color":{"text":"#7d7d82"}}}}} /-->
					</div>
					<!-- /wp:group -->

					<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group">
						<!-- wp:paragraph {"style":{"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontSize":"16px","letterSpacing":"0.02em","lineHeight":"1.5"}}} -->
						<p class="has-text-color has-link-color" style="color:#69696c;font-size:16px;letter-spacing:0.02em;line-height:1.5">
							<?php echo esc_html( $scene['group']['rating'] ); ?>
						</p>
						<!-- /wp:paragraph -->

						<!-- wp:group {"style":{"spacing":{"blockGap":"4px","margin":{"bottom":"2px"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
						<div class="wp-block-group" style="margin-bottom:2px">
							<!-- wp:icon {"icon":"events-certificates\/star","style":{"dimensions":{"width":"16px"},"color":{"text":"#d63b63"},"elements":{"link":{"color":{"text":"#d63b63"}}}}} /-->

							<!-- wp:icon {"icon":"events-certificates\/star","style":{"dimensions":{"width":"16px"},"color":{"text":"#d63b63"},"elements":{"link":{"color":{"text":"#d63b63"}}}}} /-->

							<!-- wp:icon {"icon":"events-certificates\/star","style":{"dimensions":{"width":"16px"},"color":{"text":"#d63b63"},"elements":{"link":{"color":{"text":"#d63b63"}}}}} /-->

							<!-- wp:icon {"icon":"events-certificates\/star","style":{"dimensions":{"width":"16px"},"color":{"text":"#d63b63"},"elements":{"link":{"color":{"text":"#d63b63"}}}}} /-->

							<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
							<div class="wp-block-group">
								<!-- wp:icon {"icon":"events-certificates\/star","style":{"dimensions":{"width":"16px"},"color":{"text":"#d63b63"},"elements":{"link":{"color":{"text":"#d63b63"}}},"css":"width:8px;overflow:hidden"}} /-->

								<!-- wp:icon {"icon":"events-certificates\/star","style":{"dimensions":{"width":"16px"},"color":{"text":"#d0d0d4"},"elements":{"link":{"color":{"text":"#d0d0d4"}}},"css":"width:8px;overflow:hidden;& svg{transform:translateX(-50%)}"}} /-->
							</div>
							<!-- /wp:group -->
						</div>
						<!-- /wp:group -->

						<!-- wp:paragraph {"style":{"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}},"typography":{"fontSize":"16px","letterSpacing":"0.02em","lineHeight":"1.5"}}} -->
						<p class="has-text-color has-link-color" style="color:#69696c;font-size:16px;letter-spacing:0.02em;line-height:1.5">
							<?php echo esc_html( $scene['group']['reviews'] ); ?>
						</p>
						<!-- /wp:paragraph -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"style":{"spacing":{"blockGap":"28px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">
					<!-- wp:paragraph {"style":{"typography":{"fontSize":"24px","fontStyle":"normal","fontWeight":"700","lineHeight":"1.25"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}},"spacing":{"margin":{"top":"-0.8px","bottom":"0.8px"}}}} -->
					<p class="has-text-color has-link-color" style="color:#232326;margin-top:-0.8px;margin-bottom:0.8px;font-size:24px;font-style:normal;font-weight:700;line-height:1.25">
						<?php echo esc_html( $labels['details'] ); ?>
					</p>
					<!-- /wp:paragraph -->

					<!-- wp:icon {"icon":"events-certificates\/simple-horizontal-line","style":{"color":{"text":"#ffab77"},"elements":{"link":{"color":{"text":"#ffab77"}}},"css":"position:absolute;left:-2px;bottom:-20px;width:100%;& svg{width:100%;height:35px}"}} /-->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"blockGap":"10px"},"typography":{"fontSize":"16px","lineHeight":"1.5"},"color":{"text":"#000000"}},"layout":{"type":"constrained"}} -->
				<div class="wp-block-group has-text-color has-link-color" style="font-size:16px;line-height:1.5;color:#000000;">
					<?php
					$evcert_description = wpautop( $scene['description'] );
					$evcert_description = preg_replace( '/<p>/', "<!-- wp:paragraph -->\n<p>", $evcert_description );
					$evcert_description = preg_replace( '#</p>#', "</p>\n<!-- /wp:paragraph -->", $evcert_description );
					echo wp_kses_post( $evcert_description );
					?>
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"","style":{"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px"},"blockGap":"40px"},"css":"box-sizing:border-box;max-width:528px"}} -->
		<div class="wp-block-column has-custom-css" style="padding-top:20px;padding-bottom:20px;padding-left:20px">
			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"css":"position:absolute;top:0;bottom:0;left:10px;right:10px"},"layout":{"type":"constrained"}} -->
				<div class="wp-block-group has-custom-css">
					<!-- wp:group {"style":{"css":"transform:rotate(4deg);width:100%;height:100%"},"layout":{"type":"constrained"}} -->
					<div class="wp-block-group has-custom-css">
						<!-- wp:group {"style":{"background":{"gradient":"linear-gradient(180deg,rgba(18,18,21,0.12) 0%,rgba(18,18,21,0.05) 100%)"},"border":{"radius":{"topLeft":"32px","topRight":"32px","bottomLeft":"32px","bottomRight":"32px"}},"css":"width:100%;height:100%"},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group has-custom-css" style="border-top-left-radius:32px;border-top-right-radius:32px;border-bottom-left-radius:32px;border-bottom-right-radius:32px"></div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"padding":{"top":"8px","bottom":"8px","left":"8px","right":"8px"}},"border":{"color":"#1212150d","width":"1px","radius":{"topLeft":"32px","topRight":"32px","bottomLeft":"32px","bottomRight":"32px"}},"css":"box-shadow: 0 2px 6px -2px rgba(0,0,16,0.05),0 2px 6px -2px rgba(0,0,16,0.03);","color":{"background":"#ffffff"}},"layout":{"type":"constrained"}} -->
				<div class="wp-block-group has-border-color has-custom-css has-background" style="border-color:#1212150d;border-width:1px;border-top-left-radius:32px;border-top-right-radius:32px;border-bottom-left-radius:32px;border-bottom-right-radius:32px;padding-top:8px;padding-right:8px;padding-bottom:8px;padding-left:8px;background-color:#ffffff;">
					<!-- wp:image {"aspectRatio":"16/9","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} -->
					<figure class="wp-block-image size-full has-custom-border">
						<img src="<?php echo esc_url( $scene['events'][0]['photo'] ); ?>" alt="" style="border-top-left-radius:24px;border-top-right-radius:24px;border-bottom-left-radius:24px;border-bottom-right-radius:24px;aspect-ratio:16/9;object-fit:cover"/>
					</figure>
					<!-- /wp:image -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"grid","columnCount":4}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"spacing":{"padding":{"top":"10px","bottom":"10px","left":"4px","right":"4px"},"blockGap":"2px"},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"background":"#f2f2f3"},"css":"position:relative"},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
				<div class="wp-block-group has-background" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;background-color:#f2f2f3;padding-top:10px;padding-right:4px;padding-bottom:10px;padding-left:4px">
					<!-- wp:icon {"icon":"events-certificates\/people","style":{"dimensions":{"width":"20px"},"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}}}} /-->

					<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em","lineHeight":"1.33333333"}}} -->
					<p style="font-size:12px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.33333333">
						<?php echo esc_html( $labels['attendees'] ); ?>
					</p>
					<!-- /wp:paragraph -->

					<!-- wp:group {"className":"evcert-tsi-frame","style":{"css":"position:absolute;top:-16.4px;right:-16.8px;bottom:-10.5px;left:-32px;border:7.7px solid #e93421;border-radius:10px;box-sizing:border-box;pointer-events:none;z-index:3"},"layout":{"type":"default"}} -->
					<div class="wp-block-group evcert-tsi-frame has-custom-css"></div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"padding":{"top":"10px","bottom":"10px","left":"4px","right":"4px"},"blockGap":"2px"},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"background":"#f2f2f3"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
				<div class="wp-block-group has-background" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;background-color:#f2f2f3;padding-top:10px;padding-right:4px;padding-bottom:10px;padding-left:4px">
					<!-- wp:icon {"icon":"events-certificates\/copy","style":{"dimensions":{"width":"20px"},"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}}}} /-->

					<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em","lineHeight":"1.33333333"}}} -->
					<p style="font-size:12px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.33333333">
						<?php echo esc_html( $labels['duplicate'] ); ?>
					</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"padding":{"top":"10px","bottom":"10px","left":"4px","right":"4px"},"blockGap":"2px"},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"background":"#f2f2f3"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
				<div class="wp-block-group has-background" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;background-color:#f2f2f3;padding-top:10px;padding-right:4px;padding-bottom:10px;padding-left:4px">
					<!-- wp:icon {"icon":"events-certificates\/envelope","style":{"dimensions":{"width":"20px"},"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}}}} /-->

					<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em","lineHeight":"1.33333333"}}} -->
					<p style="font-size:12px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.33333333">
						<?php echo esc_html( $labels['contact'] ); ?>
					</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"spacing":{"padding":{"top":"10px","bottom":"10px","left":"4px","right":"4px"},"blockGap":"2px"},"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}},"color":{"background":"#f2f2f3"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
				<div class="wp-block-group has-background" style="border-top-left-radius:16px;border-top-right-radius:16px;border-bottom-left-radius:16px;border-bottom-right-radius:16px;background-color:#f2f2f3;padding-top:10px;padding-right:4px;padding-bottom:10px;padding-left:4px">
					<!-- wp:icon {"icon":"events-certificates\/comment","style":{"dimensions":{"width":"20px"},"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}}}} /-->

					<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em","lineHeight":"1.33333333"}}} -->
					<p style="font-size:12px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.33333333">
						<?php echo esc_html( $labels['feedback'] ); ?>
					</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:group {"style":{"color":{"background":"#ffffff"},"css":"box-shadow:0 12px 12px -6px rgba(0,0,16,0.02),0 4px 4px -2px rgba(0,0,16,0.01),0 2px 2px -1px rgba(0,0,16,0.01),0 0 0 1px rgba(0,0,16,0.03);width:100%","layout":{"selfStretch":"fit","flexSize":null},"border":{"radius":{"topLeft":"28px","topRight":"28px","bottomLeft":"28px","bottomRight":"28px"}},"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
				<div class="wp-block-group has-custom-css" style="border-top-left-radius:28px;background-color:#ffffff;border-top-right-radius:28px;border-bottom-left-radius:28px;border-bottom-right-radius:28px">
					<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"padding":{"right":"20px","left":"20px"},"blockGap":"16px"},"css":"width:100%"},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-custom-css" style="padding-right:20px;padding-left:20px">
						<!-- wp:image {"aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"32px"}}} -->
						<figure class="wp-block-image size-full">
							<img src="<?php echo esc_url( $scene['ui']['calendar'] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover"/>
						</figure>
						<!-- /wp:image -->

						<!-- wp:group {"style":{"spacing":{"padding":{"top":"20px","bottom":"20px"},"blockGap":"0"},"css":"flex-grow:1","border":{"bottom":{"color":"#ebebee","width":"1px"}}},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group has-custom-css" style="border-bottom-color:#ebebee;border-bottom-width:1px;padding-top:20px;padding-bottom:20px">
							<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.5","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}}}} -->
							<p class="has-text-color has-link-color" style="color:#232326;font-size:16px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.5">
								<?php echo esc_html( $scene['range'] ); ?>
							</p>
							<!-- /wp:paragraph -->
						</div>
						<!-- /wp:group -->

						<!-- wp:icon {"icon":"events-certificates\/calendar-plus","style":{"dimensions":{"width":"20px"}}} /-->
					</div>
					<!-- /wp:group -->

					<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"padding":{"right":"20px","left":"20px"},"blockGap":"16px"},"css":"width:100%"},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-custom-css" style="padding-right:20px;padding-left:20px">
						<!-- wp:image {"aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"32px"}}} -->
						<figure class="wp-block-image size-full">
							<img src="<?php echo esc_url( $scene['ui']['pin'] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover"/>
						</figure>
						<!-- /wp:image -->

						<!-- wp:group {"style":{"spacing":{"padding":{"top":"20px","bottom":"20px"},"blockGap":"0"},"css":"flex-grow:1"},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group has-custom-css" style="padding-top:20px;padding-bottom:20px">
							<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.5","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}}}} -->
							<p class="has-text-color has-link-color" style="color:#232326;font-size:16px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.5">
								<?php echo esc_html( $scene['venue']['name'] ); ?>
							</p>
							<!-- /wp:paragraph -->

							<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.5","letterSpacing":"0.02em"},"color":{"text":"#69696c"},"elements":{"link":{"color":{"text":"#69696c"}}}}} -->
							<p class="has-text-color has-link-color" style="color:#69696c;font-size:16px;letter-spacing:0.02em;line-height:1.5">
								<?php echo esc_html( $scene['venue']['address'] ); ?>
							</p>
							<!-- /wp:paragraph -->
						</div>
						<!-- /wp:group -->

						<!-- wp:icon {"icon":"events-certificates\/external","style":{"dimensions":{"width":"20px"}}} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"style":{"color":{"background":"#ffffff"},"css":"box-shadow:0 12px 12px -6px rgba(0,0,16,0.02),0 4px 4px -2px rgba(0,0,16,0.01),0 2px 2px -1px rgba(0,0,16,0.01),0 0 0 1px rgba(0,0,16,0.03);width:100%","layout":{"selfStretch":"fit","flexSize":null},"border":{"top":{"color":"#ebebee","width":"1px"},"radius":{"topLeft":"28px","topRight":"28px","bottomLeft":"28px","bottomRight":"28px"},"right":[],"bottom":[],"left":[]},"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
				<div class="wp-block-group has-custom-css" style="border-top-left-radius:28px;background-color:#ffffff;border-top-right-radius:28px;border-bottom-left-radius:28px;border-bottom-right-radius:28px;border-top-color:#ebebee;border-top-width:1px">
					<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"padding":{"right":"24px","left":"24px","top":"16px","bottom":"16px"},"blockGap":"16px"},"css":"width:100%"},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
					<div class="wp-block-group has-custom-css" style="padding-top:16px;padding-right:24px;padding-bottom:16px;padding-left:24px">
						<!-- wp:image {"aspectRatio":"1","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"layout":{"selfStretch":"fixedNoShrink","flexSize":"32px"}}} -->
						<figure class="wp-block-image size-full">
							<img src="<?php echo esc_url( $scene['ui']['ticket'] ); ?>" alt="" style="aspect-ratio:1;object-fit:cover"/>
						</figure>
						<!-- /wp:image -->

						<!-- wp:group {"style":{"spacing":{"blockGap":"0"},"css":"flex-grow:1"},"layout":{"type":"constrained"}} -->
						<div class="wp-block-group has-custom-css">
							<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.5","fontStyle":"normal","fontWeight":"500","letterSpacing":"0.02em"},"color":{"text":"#232326"},"elements":{"link":{"color":{"text":"#232326"}}}}} -->
							<p class="has-text-color has-link-color" style="color:#232326;font-size:16px;font-style:normal;font-weight:500;letter-spacing:0.02em;line-height:1.5">
								<?php echo esc_html( $labels['ticket'] ); ?>
							</p>
							<!-- /wp:paragraph -->

							<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","lineHeight":"1.42857143","letterSpacing":"0.02em","fontStyle":"normal","fontWeight":"600"},"color":{"text":"#7661d2"},"elements":{"link":{"color":{"text":"#7661d2"}}}}} -->
							<p class="has-text-color has-link-color" style="color:#7661d2;font-size:14px;font-style:normal;font-weight:600;letter-spacing:0.02em;line-height:1.42857143">
								<?php echo esc_html( $labels['view_ticket'] ); ?>
							</p>
							<!-- /wp:paragraph -->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
