<?php
defined( 'ABSPATH' ) || exit;
$o = KontenKit_Settings::get();
$opt = KontenKit_Settings::OPTION;
?>
<div class="wrap kk-admin">
	<header class="kk-admin__hero">
		<div>
			<h1>KontenKit Core <span class="kk-badge">v<?php echo esc_html( KONTENKIT_CORE_VERSION ); ?></span></h1>
			<p><?php esc_html_e( 'Suite premium untuk tema blog & magazine — tanpa license key.', 'kontenkit-core' ); ?></p>
		</div>
		<div class="kk-admin__status kk-admin__status--ok"><?php esc_html_e( 'Aktif', 'kontenkit-core' ); ?></div>
	</header>

	<form method="post" action="options.php" class="kk-admin__form">
		<?php settings_fields( 'kontenkit_core' ); ?>
		<nav class="kk-tabs" role="tablist">
			<button type="button" class="kk-tabs__btn is-active" data-tab="general"><?php esc_html_e( 'Umum', 'kontenkit-core' ); ?></button>
			<button type="button" class="kk-tabs__btn" data-tab="article"><?php esc_html_e( 'Artikel', 'kontenkit-core' ); ?></button>
			<button type="button" class="kk-tabs__btn" data-tab="content"><?php esc_html_e( 'Konten & CPT', 'kontenkit-core' ); ?></button>
			<button type="button" class="kk-tabs__btn" data-tab="seo"><?php esc_html_e( 'SEO', 'kontenkit-core' ); ?></button>
			<button type="button" class="kk-tabs__btn" data-tab="shortcodes"><?php esc_html_e( 'Shortcode', 'kontenkit-core' ); ?></button>
		</nav>

		<div class="kk-panel is-active" id="tab-general">
			<div class="kk-grid">
				<div class="kk-card">
					<h2><?php esc_html_e( 'Brand', 'kontenkit-core' ); ?></h2>
					<p><label><?php esc_html_e( 'Warna utama', 'kontenkit-core' ); ?>
						<input type="text" class="kk-color" name="<?php echo esc_attr( $opt ); ?>[brand_color]" value="<?php echo esc_attr( $o['brand_color'] ); ?>" data-default-color="#1e3a5f" />
					</label></p>
					<p><label><?php esc_html_e( 'Aksen', 'kontenkit-core' ); ?>
						<input type="text" class="kk-color" name="<?php echo esc_attr( $opt ); ?>[accent_color]" value="<?php echo esc_attr( $o['accent_color'] ); ?>" data-default-color="#c9a227" />
					</label></p>
				</div>
				<div class="kk-card">
					<h2><?php esc_html_e( 'Pemeliharaan', 'kontenkit-core' ); ?></h2>
					<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[maintenance]" value="1" <?php checked( $o['maintenance'], 1 ); ?> /> <?php esc_html_e( 'Mode maintenance (hanya admin)', 'kontenkit-core' ); ?></label>
					<p><textarea class="large-text" rows="3" name="<?php echo esc_attr( $opt ); ?>[maintenance_msg]"><?php echo esc_textarea( $o['maintenance_msg'] ); ?></textarea></p>
				</div>
			</div>
			<div class="kk-card">
				<h2><?php esc_html_e( 'CSS kustom', 'kontenkit-core' ); ?></h2>
				<textarea class="large-text code" rows="8" name="<?php echo esc_attr( $opt ); ?>[custom_css]" placeholder=".entry-title { letter-spacing: .02em; }"><?php echo esc_textarea( $o['custom_css'] ); ?></textarea>
			</div>
		</div>

		<div class="kk-panel" id="tab-article">
			<div class="kk-grid">
				<div class="kk-card">
					<h2><?php esc_html_e( 'Tampilan artikel', 'kontenkit-core' ); ?></h2>
					<?php
					$flags = [
						'post_meta_bar' => __( 'Bar meta (tanggal, penulis, views)', 'kontenkit-core' ),
						'reading_time'  => __( 'Estimasi waktu baca', 'kontenkit-core' ),
						'post_views'    => __( 'Penghitung views', 'kontenkit-core' ),
						'table_of_contents' => __( 'Daftar isi otomatis', 'kontenkit-core' ),
						'breadcrumbs'   => __( 'Breadcrumb', 'kontenkit-core' ),
					];
					foreach ( $flags as $k => $label ) :
						?>
						<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( $o[ $k ], 1 ); ?> /> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
					<p><label><?php esc_html_e( 'Min. heading untuk TOC', 'kontenkit-core' ); ?>
						<input type="number" min="2" max="8" class="small-text" name="<?php echo esc_attr( $opt ); ?>[toc_min_headings]" value="<?php echo esc_attr( (string) $o['toc_min_headings'] ); ?>" />
					</label></p>
				</div>
				<div class="kk-card">
					<h2><?php esc_html_e( 'Setelah artikel', 'kontenkit-core' ); ?></h2>
					<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[social_share]" value="1" <?php checked( $o['social_share'], 1 ); ?> /> <?php esc_html_e( 'Bagikan sosial', 'kontenkit-core' ); ?></label>
					<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[sticky_share]" value="1" <?php checked( $o['sticky_share'], 1 ); ?> /> <?php esc_html_e( 'Share sticky (desktop)', 'kontenkit-core' ); ?></label>
					<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[author_box]" value="1" <?php checked( $o['author_box'], 1 ); ?> /> <?php esc_html_e( 'Kotak penulis premium', 'kontenkit-core' ); ?></label>
					<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[related_posts]" value="1" <?php checked( $o['related_posts'], 1 ); ?> /> <?php esc_html_e( 'Artikel terkait', 'kontenkit-core' ); ?></label>
					<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[related_grid]" value="1" <?php checked( $o['related_grid'], 1 ); ?> /> <?php esc_html_e( 'Grid dengan thumbnail', 'kontenkit-core' ); ?></label>
					<p><label><?php esc_html_e( 'Jumlah terkait', 'kontenkit-core' ); ?> <input type="number" min="2" max="12" class="small-text" name="<?php echo esc_attr( $opt ); ?>[related_count]" value="<?php echo esc_attr( (string) $o['related_count'] ); ?>" /></label></p>
					<p><label><?php esc_html_e( 'Panjang excerpt', 'kontenkit-core' ); ?> <input type="number" min="10" max="80" class="small-text" name="<?php echo esc_attr( $opt ); ?>[excerpt_length]" value="<?php echo esc_attr( (string) $o['excerpt_length'] ); ?>" /> <?php esc_html_e( 'kata', 'kontenkit-core' ); ?></label></p>
				</div>
			</div>
			<div class="kk-card">
				<h2><?php esc_html_e( 'Newsletter box', 'kontenkit-core' ); ?></h2>
				<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[newsletter]" value="1" <?php checked( $o['newsletter'], 1 ); ?> /> <?php esc_html_e( 'Tampilkan di artikel', 'kontenkit-core' ); ?></label>
				<p><input type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[newsletter_title]" value="<?php echo esc_attr( $o['newsletter_title'] ); ?>" /></p>
				<p><textarea class="large-text" rows="2" name="<?php echo esc_attr( $opt ); ?>[newsletter_text]"><?php echo esc_textarea( $o['newsletter_text'] ); ?></textarea></p>
				<p><input type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[newsletter_btn]" value="<?php echo esc_attr( $o['newsletter_btn'] ); ?>" /></p>
			</div>
		</div>

		<div class="kk-panel" id="tab-content">
			<div class="kk-card">
				<h2><?php esc_html_e( 'Custom Post Types', 'kontenkit-core' ); ?></h2>
				<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[cpt_portfolio]" value="1" <?php checked( $o['cpt_portfolio'], 1 ); ?> /> <?php esc_html_e( 'Portfolio (kk_portfolio)', 'kontenkit-core' ); ?></label>
				<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[cpt_testimonial]" value="1" <?php checked( $o['cpt_testimonial'], 1 ); ?> /> <?php esc_html_e( 'Testimonial (kk_testimonial)', 'kontenkit-core' ); ?></label>
				<p class="description"><?php esc_html_e( 'Simpan lalu buka Settings → Permalinks → Save sekali jika archive 404.', 'kontenkit-core' ); ?></p>
			</div>
		</div>

		<div class="kk-panel" id="tab-seo">
			<div class="kk-card">
				<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[og_meta]" value="1" <?php checked( $o['og_meta'], 1 ); ?> /> <?php esc_html_e( 'Open Graph (Facebook, WhatsApp preview)', 'kontenkit-core' ); ?></label>
				<label class="kk-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[schema_article]" value="1" <?php checked( $o['schema_article'], 1 ); ?> /> <?php esc_html_e( 'Schema.org Article (JSON-LD)', 'kontenkit-core' ); ?></label>
			</div>
		</div>

		<div class="kk-panel" id="tab-shortcodes">
			<div class="kk-card kk-shortcodes-ref">
				<h2><?php esc_html_e( 'Daftar shortcode', 'kontenkit-core' ); ?></h2>
				<table class="widefat striped">
					<thead><tr><th>Shortcode</th><th>Contoh</th></tr></thead>
					<tbody>
						<tr><td><code>[kk_button]</code></td><td><code>[kk_button url="/" text="Mulai" style="accent"]</code></td></tr>
						<tr><td><code>[kk_alert]</code></td><td><code>[kk_alert type="success"]Teks[/kk_alert]</code></td></tr>
						<tr><td><code>[kk_icon_box]</code></td><td><code>[kk_icon_box icon="★" title="Judul"]Deskripsi[/kk_icon_box]</code></td></tr>
						<tr><td><code>[kk_accordion]</code></td><td><code>[kk_accordion title="Q1"]A1[/kk_accordion]</code></td></tr>
						<tr><td><code>[kk_quote]</code></td><td><code>[kk_quote author="Nama"]Kutipan[/kk_quote]</code></td></tr>
						<tr><td><code>[kk_youtube]</code></td><td><code>[kk_youtube id="VIDEO_ID"]</code></td></tr>
						<tr><td><code>[kk_pricing]</code></td><td><code>[kk_pricing name="Pro" price="99" features="A|B|C" highlighted="yes"]</code></td></tr>
						<tr><td><code>[kk_testimonials]</code></td><td><code>[kk_testimonials count="6"]</code></td></tr>
						<tr><td><code>[kk_portfolio]</code></td><td><code>[kk_portfolio count="8"]</code></td></tr>
						<tr><td><code>[kk_cta]</code></td><td><code>[kk_cta title="Hubungi" btn="Email" url="mailto:"]</code></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Simpan pengaturan', 'kontenkit-core' ), 'primary kk-submit' ); ?>
	</form>
</div>
