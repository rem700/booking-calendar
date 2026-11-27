<?php
/**
 * The admin page of the widget: the code to paste into Framer, Webflow, Tilda or Wix,
 * a preview, and the sites that may show the form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=bk_booking', __( 'Form on other sites', 'shootbook' ), __( 'Site widget', 'shootbook' ), 'manage_options', 'bkb-embed', 'bkb_embed_page' );
	},
	22
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'bk_booking_page_bkb-embed' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bkb-admin', BKB_URL . 'assets/admin.css', array(), BKB_VER );
		wp_enqueue_script( 'bkb-embed-admin', BKB_URL . 'assets/embed-admin.js', array(), BKB_VER, true );
		$s = (array) bkb_cfg( 'style' );
		wp_add_inline_script(
			'bkb-embed-admin',
			'window.BKB_EMBED=' . wp_json_encode(
				array(
					'form'   => bkb_embed_url(),
					'script' => bkb_embed_script_url(),
					'style'  => array(
						'ink'    => $s['ink'],
						'paper'  => $s['paper'],
						'accent' => $s['accent'],
					),
					'copied' => __( 'Copied', 'shootbook' ),
					'lang'   => bkb_admin_lang(),
				)
			) . ';',
			'before'
		);
	}
);

add_action(
	'admin_post_bkb_embed_sites',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_embed_sites' );
		bkb_embed_save( sanitize_textarea_field( wp_unslash( (string) ( $_POST['sites'] ?? '' ) ) ) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=bk_booking&page=bkb-embed&bkb_saved=1#bkb-embed-sites' ) );
		exit;
	}
);

function bkb_embed_page() {
	$langs = bkb_lang_names();
	$saved = ! empty( $_GET['bkb_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="wrap bkb-embed-admin">
		<h1><?php esc_html_e( 'Form on other sites', 'shootbook' ); ?></h1>
		<p class="bkb-lead"><?php esc_html_e( 'Show the booking form on a site made in Framer, Webflow, Tilda or Wix. Requests come here as usual: to the list of requests, to the clients and to your e-mail.', 'shootbook' ); ?></p>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'shootbook' ); ?></p></div>
		<?php endif; ?>

		<div class="bkb-embed-cols">
			<div class="bkb-embed-box">
				<h2><?php esc_html_e( 'Code for the site', 'shootbook' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'View', 'shootbook' ); ?></th>
						<td>
							<label class="bkb-inline"><input type="radio" name="bkb-e-mode" value="" checked> <?php esc_html_e( 'Form on the page', 'shootbook' ); ?></label>
							<label class="bkb-inline"><input type="radio" name="bkb-e-mode" value="button"> <?php esc_html_e( 'Button that opens the form', 'shootbook' ); ?></label>
							<p class="bkb-e-label" hidden><label><?php esc_html_e( 'Button text', 'shootbook' ); ?><br><input type="text" class="regular-text" id="bkb-e-label" value=""></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bkb-e-lang"><?php esc_html_e( 'Language', 'shootbook' ); ?></label></th>
						<td>
							<select id="bkb-e-lang">
								<option value=""><?php esc_html_e( 'Language of the page', 'shootbook' ); ?></option>
								<?php foreach ( bkb_langs() as $l ) : ?>
									<option value="<?php echo esc_attr( $l ); ?>"><?php echo esc_html( $langs[ $l ] ?? $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Colours', 'shootbook' ); ?></th>
						<td>
							<label class="bkb-inline"><input type="radio" name="bkb-e-theme" value="" checked> <?php esc_html_e( 'From the settings', 'shootbook' ); ?></label>
							<label class="bkb-inline"><input type="radio" name="bkb-e-theme" value="inherit"> <?php esc_html_e( 'Like the site', 'shootbook' ); ?></label>
							<label class="bkb-inline"><input type="radio" name="bkb-e-theme" value="own"> <?php esc_html_e( 'Own', 'shootbook' ); ?></label>
							<p class="bkb-e-colors" hidden>
								<label class="bkb-inline"><input type="color" id="bkb-e-ink"> <?php esc_html_e( 'Text', 'shootbook' ); ?></label>
								<label class="bkb-inline"><input type="color" id="bkb-e-paper"> <?php esc_html_e( 'Background', 'shootbook' ); ?></label>
								<label class="bkb-inline"><input type="color" id="bkb-e-accent"> <?php esc_html_e( 'Accent', 'shootbook' ); ?></label>
							</p>
							<p class="description bkb-e-inherit" hidden><?php esc_html_e( 'The form takes the text colour, the background and the font of the place where the code is pasted.', 'shootbook' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bkb-e-font"><?php esc_html_e( 'Font', 'shootbook' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bkb-e-font" placeholder="Inter">
							<p><label><input type="checkbox" id="bkb-e-gf"> <?php esc_html_e( 'Load the font from Google Fonts', 'shootbook' ); ?></label></p>
							<p class="description"><?php esc_html_e( 'Leave empty to keep the font from the settings.', 'shootbook' ); ?></p>
						</td>
					</tr>
				</table>
				<p><textarea id="bkb-e-code" class="large-text code" rows="5" readonly></textarea></p>
				<p><button type="button" class="button button-primary" id="bkb-e-copy"><?php esc_html_e( 'Copy the code', 'shootbook' ); ?></button> <span class="bkb-e-done" aria-live="polite"></span></p>
			</div>

			<div class="bkb-embed-box">
				<h2><?php esc_html_e( 'Where to paste it', 'shootbook' ); ?></h2>
				<details open>
					<summary>Framer</summary>
					<p><?php esc_html_e( 'Add an Embed block (Insert → Embed), choose HTML and paste the code. Make the block as wide as the page.', 'shootbook' ); ?></p>
				</details>
				<details>
					<summary>Webflow</summary>
					<p><?php esc_html_e( 'Add a Code Embed element, paste the code and save. The form shows on the published site.', 'shootbook' ); ?></p>
				</details>
				<details>
					<summary>Tilda</summary>
					<p><?php esc_html_e( 'Add the block T123 (Other → HTML code), paste the code and publish the page.', 'shootbook' ); ?></p>
				</details>
				<details>
					<summary>Wix</summary>
					<p><?php esc_html_e( 'Add Embed Code → Embed HTML, choose Code and paste it. Wix keeps the block at the height you set, so make it tall enough for the whole form.', 'shootbook' ); ?></p>
				</details>
				<p><?php esc_html_e( 'If the form is cut at the bottom, the builder keeps the block at a fixed height. Make the block taller or choose the button view.', 'shootbook' ); ?></p>
				<h3><?php esc_html_e( 'Link to the form', 'shootbook' ); ?></h3>
				<p><?php esc_html_e( 'For a block that takes only an address. The height does not adjust itself here.', 'shootbook' ); ?></p>
				<p><input type="text" class="large-text code" readonly id="bkb-e-url" onclick="this.select()"></p>
			</div>
		</div>

		<div class="bkb-embed-box">
			<h2><?php esc_html_e( 'Preview', 'shootbook' ); ?></h2>
			<div id="bkb-e-preview" class="bkb-e-preview"></div>
		</div>

		<div class="bkb-embed-box" id="bkb-embed-sites">
			<h2><?php esc_html_e( 'Sites that may show the form', 'shootbook' ); ?></h2>
			<p><?php esc_html_e( 'One address per line, for example https://iparstudio.com. Other sites cannot show the form. On these sites the link to book again in the client’s e-mails leads to the page with the form.', 'shootbook' ); ?></p>
			<p><?php esc_html_e( 'If the list is empty, any site can show the form.', 'shootbook' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bkb_embed_sites">
				<?php wp_nonce_field( 'bkb_embed_sites' ); ?>
				<p><textarea name="sites" id="bkb-e-sites" class="large-text code" rows="4"><?php echo esc_textarea( bkb_embed_get()['sites'] ); ?></textarea></p>
				<p><button type="submit" class="button"><?php esc_html_e( 'Save the list', 'shootbook' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
}
