<?php
/**
 * Resolution Centre — View
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

// ─── Helper functions ────────────────────────────────────────────────────────

function thisismyurl_shadow_rc_status( string $slug, array $findings, array $excluded, array $records ): string {
	if ( isset( $excluded[ $slug ] ) ) {
		return 'skipped';
	}

	if ( isset( $records[ $slug ] ) && ( $records[ $slug ]['status'] ?? '' ) === 'resolved' ) {
		return 'resolved';
	}

	if ( isset( $findings[ $slug ] ) ) {
		return 'issue';
	}

	return 'passing';
}

function thisismyurl_shadow_rc_badge( string $status ): string {
	$labels = array(
		'issue'    => 'Needs Attention',
		'passing'  => 'Looks Good',
		'resolved' => 'Resolved',
		'skipped'  => 'Skipped',
	);

	return '<span class="wps-res-badge ' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? $labels['passing'] ) . '</span>';
}

function thisismyurl_shadow_rc_card_open( string $slug, string $title, string $status ): void {
	$did = 'wps-rc-' . $slug;
	echo '<div class="wps-res-card wps-res-card--' . esc_attr( $status ) . '" id="' . esc_attr( $did ) . '" data-slug="' . esc_attr( $slug ) . '">';
	echo '<div class="wps-res-card__header" role="button" tabindex="0" aria-expanded="false" aria-controls="' . esc_attr( $did ) . '-body">';
	echo '<div class="wps-res-card__title-row"><strong class="wps-res-card__title">' . esc_html( $title ) . '</strong>' . wp_kses_post( thisismyurl_shadow_rc_badge( $status ) ) . '</div>';
	echo '<span class="wps-res-card__toggle-icon" aria-hidden="true">&#9660;</span>';
	echo '</div><div class="wps-res-card__body" id="' . esc_attr( $did ) . '-body" hidden>';
}

function thisismyurl_shadow_rc_card_close( string $slug, string $status, string $nonce ): void {
	echo '<div class="wps-res-card__actions">';

	if ( 'resolved' !== $status ) {
		echo '<button class="button button-primary wps-rc-btn" data-action="resolved" data-slug="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( $nonce ) . '">Mark Resolved</button>';
	}

	if ( 'skipped' !== $status ) {
		echo '<button class="button wps-rc-btn" data-action="skipped" data-slug="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( $nonce ) . '">Skip in Future Scans</button>';
	}

	if ( in_array( $status, array( 'resolved', 'skipped' ), true ) ) {
		echo '<button class="button wps-rc-btn" data-action="pending" data-slug="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( $nonce ) . '">Undo</button>';
	}

	echo '<span class="wps-res-feedback-msg" aria-live="polite"></span></div></div></div>';
}

function thisismyurl_shadow_rc_admin_link( string $url, string $label ): void {
	echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html( $label ) . ' &rarr;</a> ';
}

function thisismyurl_shadow_rc_action_open( string $heading ): void {
	echo '<div class="wps-res-action-panel"><h4>' . esc_html( $heading ) . '</h4><div class="wps-res-action-links">';
}

function thisismyurl_shadow_rc_action_close(): void {
	echo '</div></div>';
}

function thisismyurl_shadow_rc_option_row( string $option_name, string $label, string $current_display, string $new_value, string $btn_label, string $nonce ): void {
	$uid = 'wps-opt-' . sanitize_key( $option_name . '-' . $new_value );
	echo '<div class="wps-res-option-row">';
	echo '<div><div class="wps-res-option-label">' . esc_html( $label ) . '</div>';
	echo '<div class="wps-res-option-current">Currently: <strong>' . esc_html( $current_display ) . '</strong></div></div>';
	echo '<button class="button wps-rc-save-option wps-res-save-option-btn" id="' . esc_attr( $uid ) . '" data-option="' . esc_attr( $option_name ) . '" data-value="' . esc_attr( $new_value ) . '" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html( $btn_label ) . '</button>';
	echo '<span class="wps-res-option-feedback" aria-live="polite"></span></div>';
}


// ──────────────────────────────────────────────────────── render callback ────

function thisismyurl_shadow_render_resolution_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$raw      = get_option( 'thisismyurl_shadow_site_findings', array() );
	$findings = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $f ) {
			if ( ! empty( $f['id'] ) ) {
				$findings[ $f['id'] ] = $f;
			}
		}
	}

	$excluded = get_option( 'thisismyurl_shadow_excluded_findings', array() );
	if ( ! is_array( $excluded ) ) {
		$excluded = array();
	}

	$records = get_option( 'thisismyurl_shadow_resolution_records', array() );
	if ( ! is_array( $records ) ) {
		$records = array();
	}

	$nonce = wp_create_nonce( 'thisismyurl_shadow_resolution' );

	$site_title        = (string) get_bloginfo( 'name' );
	$tagline           = (string) get_bloginfo( 'description' );
	$registration_open = (bool) get_option( 'users_can_register', 0 );
	$comment_status    = (string) get_option( 'default_comment_status', 'closed' );
	$comment_mod       = (bool) get_option( 'comment_moderation', 0 );
	$ping_sites        = trim( (string) get_option( 'ping_sites', '' ) );
	$auto_update_core  = get_option( 'wp_auto_update_core', 'minor' );
	$homepage_id       = (int) get_option( 'page_on_front', 0 );
	$posts_page_id     = (int) get_option( 'page_for_posts', 0 );
	$show_on_front     = (string) get_option( 'show_on_front', 'posts' );
	$site_icon_id      = (int) get_option( 'site_icon', 0 );
	$logo_id           = (int) get_theme_mod( 'custom_logo', 0 );
	$nav_assignments   = (array) get_theme_mod( 'nav_menu_locations', array() );
	$nav_locations     = get_registered_nav_menus();

	$seo_plugin = $seo_titles_url = $seo_social_url = $seo_schema_url = $seo_robots_url = '';
	if ( defined( 'WPSEO_VERSION' ) || class_exists( '\\WPSEO_Options' ) ) {
		$seo_plugin    = 'Yoast SEO';
		$seo_titles_url = admin_url( 'admin.php?page=wpseo_titles' );
		$seo_social_url = admin_url( 'admin.php?page=wpseo_social' );
		$seo_schema_url = admin_url( 'admin.php?page=wpseo_titles' );
		$seo_robots_url = admin_url( 'admin.php?page=wpseo_tools' );
	} elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath\\RankMath' ) ) {
		$seo_plugin     = 'Rank Math';
		$seo_titles_url = $seo_social_url = $seo_schema_url = admin_url( 'admin.php?page=rank-math-options-titles' );
		$seo_robots_url = admin_url( 'admin.php?page=rank-math-status' );
	} elseif ( defined( 'AIOSEOP_VERSION' ) || class_exists( '\\AIOSEO\\Plugin\\AIOSEO' ) ) {
		$seo_plugin     = 'All in One SEO';
		$seo_titles_url = $seo_schema_url = admin_url( 'admin.php?page=aioseo-search-appearance' );
		$seo_social_url = admin_url( 'admin.php?page=aioseo-social-networks' );
		$seo_robots_url = admin_url( 'admin.php?page=aioseo-tools' );
	}

	$seo_link = static function ( string $url, string $label ) use ( $seo_plugin ): void {
		if ( $seo_plugin && $url ) {
			echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html( $label . ' in ' . $seo_plugin ) . ' &rarr;</a> ';
		} else {
			echo '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '" class="button">Review Installed Plugins &rarr;</a> ';
		}
	};

	$pub_pages = get_pages(
		array(
			'post_status' => 'publish',
			'number'      => 200,
		)
	);

	$find_page = static function ( string $kw ) use ( $pub_pages ): ?\WP_Post {
		$kw = strtolower( $kw );
		foreach ( $pub_pages as $p ) {
			if ( false !== strpos( strtolower( $p->post_title ), $kw ) ) {
				return $p;
			}
		}

		return null;
	};

	$about_page   = $find_page( 'about' );
	$contact_page = $find_page( 'contact' );

	wp_enqueue_style( 'thisismyurl-shadow-resolution-page', plugin_dir_url( THISISMYURL_SHADOW_FILE ) . 'assets/css/resolution-page.css', array(), '0.6093' );

	$all_slugs = array_unique(
		array(
			'site-title-tagline-intentional', 'homepage-meta', 'schema-basics', 'organization-schema',
			'twitter-card', 'social-profile-links', 'category-strategy', 'author-archives-intentional',
			'tag-archives-intentional', 'noindex-policy', 'robots-policy', 'custom-404-strategy-present',
			'image-alt-process', 'posts-have-featured-images', 'copyright-year-current',
			'primary-navigation-assigned', 'footer-menu', 'mobile-menu', 'site-icon', 'custom-logo-set',
			'registration-setting-intentional', 'xmlrpc-policy-intentional', 'search-enabled-intentional',
			'auto-update-policy', 'plugin-auto-updates', 'update-services-intentional',
			'analytics-installed-intentional', 'comment-policy-intentional', 'rest-api-sensitive-routes-protected',
			'about-page-published', 'contact-page-published', 'contact-page-has-form',
			'homepage-page-published', 'posts-page-published',
		)
	);

	$counts = array( 'issue' => 0, 'passing' => 0, 'resolved' => 0, 'skipped' => 0 );
	foreach ( $all_slugs as $si ) {
		$st = thisismyurl_shadow_rc_status( $si, $findings, $excluded, $records );
		if ( isset( $counts[ $st ] ) ) {
			$counts[ $st ]++;
		}
	}

	$stat_labels = array( 'issue' => 'Needs Attention', 'passing' => 'Looks Good', 'resolved' => 'Resolved', 'skipped' => 'Skipped' );
	$dashboard_url = admin_url( 'admin.php?page=thisismyurl-shadow' );
?>
<div class="wrap wps-resolution-wrap">
	<div class="wps-page-header"><a href="<?php echo esc_url( $dashboard_url ); ?>" class="wps-back-link">&larr; Back to Dashboard</a></div>
	<h1>Resolution Centre</h1>
	<p class="wps-resolution-intro">These diagnostics flag things that only you can decide. Work through each card, apply any fixes, then mark items resolved. Items that do not apply can be skipped so they will not appear in future reports.</p>
	<div class="wps-res-summary">
		<?php foreach ( $counts as $st => $n ) : ?>
			<?php if ( $n > 0 ) : ?>
				<span class="wps-res-summary-pill <?php echo esc_attr( $st ); ?>"><span class="count"><?php echo esc_html( $n ); ?></span> <?php echo esc_html( $stat_labels[ $st ] ?? $st ); ?></span>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

<?php /* GROUP 1: Content Decisions */ ?>
<div class="wps-res-section">
	<div class="wps-res-section-header"><h2>Content Decisions</h2><span class="wps-res-section-count">17 diagnostics — branding, SEO, and content strategy choices</span></div>
<?php
$s="site-title-tagline-intentional"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Site Title & Tagline",$st);
echo "<p class='wps-res-card__desc'>Your site title and tagline appear in browser tabs, search snippets, and social share previews. WordPress ships with generic placeholder text — make sure both reflect your real brand.</p>";
echo "<div class='wps-res-current-state'><strong>Title:</strong> ".esc_html($site_title?:"-")." &bull; <strong>Tagline:</strong> ".esc_html($tagline?:"-")."</div>";
thisismyurl_shadow_rc_action_open("Update in WordPress Settings");
thisismyurl_shadow_rc_admin_link(admin_url("options-general.php"),"Settings → General");
thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="homepage-meta"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Homepage Meta Title & Description",$st);
echo "<p class='wps-res-card__desc'>The meta title and description control what Google shows in search results. Set these in your SEO plugin.</p>";
thisismyurl_shadow_rc_action_open($seo_plugin ? "Set in {$seo_plugin}" : "Set in your SEO plugin");
$seo_link($seo_titles_url,"Homepage Meta"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="schema-basics"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Schema / Structured Data",$st);
echo "<p class='wps-res-card__desc'>Structured data tells search engines what your content is, unlocking rich results like star ratings and FAQs. An SEO plugin manages this automatically.</p>";
thisismyurl_shadow_rc_action_open($seo_plugin ? "Configure in {$seo_plugin}" : "Review SEO tooling");
$seo_link($seo_schema_url,"Schema Settings"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="organization-schema"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Organisation Schema",$st);
echo "<p class='wps-res-card__desc'>Organisation schema links your site to a known entity in Google's Knowledge Graph, powering the branded info panel in search results.</p>";
thisismyurl_shadow_rc_action_open("Configure in your SEO plugin");
$seo_link($seo_schema_url,"Organisation Schema"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="twitter-card"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Twitter / X Card Tags",$st);
echo "<p class='wps-res-card__desc'>Twitter cards control how your links look when shared on X. Without them posts appear as bare URLs. Enable summary_large_image cards in your SEO plugin.</p>";
thisismyurl_shadow_rc_action_open("Enable Twitter cards");
$seo_link($seo_social_url,"Social / Twitter Settings"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="social-profile-links"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Social Profile Links",$st);
echo "<p class='wps-res-card__desc'>Connecting your schema to your social accounts tells search engines those profiles belong to the same entity.</p>";
thisismyurl_shadow_rc_action_open("Add social profile URLs");
$seo_link($seo_social_url,"Social Profiles"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="category-strategy"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Category Strategy",$st);
echo "<p class='wps-res-card__desc'>Posts in the Uncategorized default category send no topical signal to search engines. Create meaningful categories and assign posts to them.</p>";
thisismyurl_shadow_rc_action_open("Manage categories");
thisismyurl_shadow_rc_admin_link(admin_url("edit-tags.php?taxonomy=category"),"Posts → Categories"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="author-archives-intentional"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Author Archives",$st);
echo "<p class='wps-res-card__desc'>On single-author sites, /author/ pages duplicate the blog, creating thin content. Noindex them in your SEO plugin or redirect them.</p>";
thisismyurl_shadow_rc_action_open("Configure indexing");
$seo_link($seo_titles_url,"Author Archive Settings"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="tag-archives-intentional"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Tag Archives",$st);
echo "<p class='wps-res-card__desc'>Tag archive pages can produce dozens of low-word-count pages competing with your main content. Decide whether to index, noindex, or consolidate tags.</p>";
thisismyurl_shadow_rc_action_open("Configure tag archive indexing");
$seo_link($seo_titles_url,"Tag Archive Settings"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="noindex-policy"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Noindex Policy for Low-Value Pages",$st);
echo "<p class='wps-res-card__desc'>Date archives, search result pages, and pagination rarely contain unique content. Add noindex directives to keep crawl budget focused on real content pages.</p>";
thisismyurl_shadow_rc_action_open("Set noindex rules");
$seo_link($seo_titles_url,"Search Appearance / Noindex");
thisismyurl_shadow_rc_admin_link(admin_url("options-reading.php"),"Settings → Reading"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="robots-policy"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Robots.txt Policy",$st);
echo "<p class='wps-res-card__desc'>robots.txt is the first file Google reads when visiting your site. A deliberately configured robots.txt focuses crawler attention on important content.</p>";
thisismyurl_shadow_rc_action_open("Review or edit robots.txt");
if ( $seo_plugin && $seo_robots_url ) {
	$seo_link( $seo_robots_url, 'robots.txt Editor' );
} else {
	thisismyurl_shadow_rc_admin_link( admin_url( 'tools.php' ), 'Tools' );
}
thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="custom-404-strategy-present"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Custom 404 Page",$st);
echo "<p class='wps-res-card__desc'>A custom 404 page turns a broken link into a helpful experience with a search box and menu. Most themes support this via a 404.php template.</p>";
thisismyurl_shadow_rc_action_open("Check theme 404 template");
thisismyurl_shadow_rc_admin_link(admin_url("edit.php?post_type=page"),"All Pages");
thisismyurl_shadow_rc_admin_link(admin_url("customize.php"),"Customiser"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="image-alt-process"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Image Alt Text Workflow",$st);
echo "<p class='wps-res-card__desc'>Alt text helps screen readers describe images and gives search engines context about your visuals. Ensure every uploaded image gets meaningful alt text before publishing.</p>";
thisismyurl_shadow_rc_action_open("Review images");
thisismyurl_shadow_rc_admin_link(admin_url("upload.php"),"Media Library"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="posts-have-featured-images"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Posts Without Featured Images",$st);
echo "<p class='wps-res-card__desc'>Featured images make blog listings visually engaging and ensure shared posts include a proper social preview image. Review recent posts and set featured images.</p>";
thisismyurl_shadow_rc_action_open("Review posts");
thisismyurl_shadow_rc_admin_link(admin_url("edit.php"),"All Posts"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

$s="copyright-year-current"; $st=thisismyurl_shadow_rc_status($s,$findings,$excluded,$records);
thisismyurl_shadow_rc_card_open($s,"Copyright Year in Footer",$st);
$cy=gmdate("Y");
echo "<p class='wps-res-card__desc'>A stale copyright year makes your site look neglected. Update your footer to show " . esc_html( $cy ) . ".</p>";
thisismyurl_shadow_rc_action_open("Edit footer");
thisismyurl_shadow_rc_admin_link(admin_url("customize.php"),"Customiser");
thisismyurl_shadow_rc_admin_link(admin_url("themes.php"),"Appearance Themes"); thisismyurl_shadow_rc_action_close(); thisismyurl_shadow_rc_card_close($s,$st,$nonce);

?>
</div>
<?php /* GROUP 2 */ ?>
<div class="wps-res-section">
	<div class="wps-res-section-header"><h2>Navigation &amp; Structure</h2><span class="wps-res-section-count">5 diagnostics</span></div>
	<?php
	$s = 'primary-navigation-assigned';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Primary Navigation Menu', $st );
	echo "<p class='wps-res-card__desc'>Without a menu assigned to the primary navigation location, visitors have no reliable way to move around your site.</p>";

	$plabel = '';
	$phas   = false;
	foreach ( $nav_locations as $lk => $ll ) {
		if ( false !== stripos( $lk, 'primary' ) || false !== stripos( $ll, 'primary' ) || false !== stripos( $ll, 'main' ) ) {
			$plabel = $ll;
			$phas   = ! empty( $nav_assignments[ $lk ] );
			break;
		}
	}

	if ( $plabel ) {
		echo "<div class='wps-res-current-state'><strong>Location:</strong> " . esc_html( $plabel ) . " &bull; " . ( $phas ? 'Menu assigned' : '<strong>No menu assigned</strong>' ) . '</div>';
	}

	thisismyurl_shadow_rc_action_open( 'Assign a menu' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'nav-menus.php' ), 'Appearance Menus' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'footer-menu';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Footer Navigation Menu', $st );
	echo "<p class='wps-res-card__desc'>A footer menu provides secondary navigation to Privacy Policy, Contact, and About. Assign a menu to your footer location or use a Custom HTML widget.</p>";
	thisismyurl_shadow_rc_action_open( 'Set up footer menu' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'nav-menus.php' ), 'Appearance Menus' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'widgets.php' ), 'Appearance Widgets' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'mobile-menu';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Mobile Navigation (Responsive Menu)', $st );
	echo "<p class='wps-res-card__desc'>More than half of web traffic is mobile. Make sure your theme collapses navigation into a hamburger or slide-out menu on small screens.</p>";
	thisismyurl_shadow_rc_action_open( 'Review mobile behaviour' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'customize.php' ), 'Customiser Preview' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'themes.php' ), 'Appearance Themes' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'site-icon';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Site Icon (Favicon)', $st );
	echo "<p class='wps-res-card__desc'>The site icon appears in browser tabs, bookmarks, and phone home-screen shortcuts. Upload a square image at least 512x512 px.</p>";
	if ( $site_icon_id ) {
		echo "<div class='wps-res-current-state'><strong>Icon set:</strong> " . wp_get_attachment_image( $site_icon_id, array( 32, 32 ) ) . '</div>';
	} else {
		echo "<div class='wps-res-current-state'><strong>No site icon set.</strong></div>";
	}
	thisismyurl_shadow_rc_action_open( 'Upload site icon' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'customize.php?autofocus[section]=title_tagline' ), 'Customiser Site Identity' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'custom-logo-set';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Custom Logo', $st );
	echo "<p class='wps-res-card__desc'>Replacing the default site name with a logo image makes the site look professionally built. SVG files scale perfectly on high-resolution screens.</p>";
	if ( $logo_id ) {
		echo "<div class='wps-res-current-state'><strong>Logo set:</strong> " . wp_get_attachment_image( $logo_id, array( 120, 40 ) ) . '</div>';
	} else {
		echo "<div class='wps-res-current-state'><strong>No custom logo set.</strong></div>";
	}
	thisismyurl_shadow_rc_action_open( 'Upload your logo' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'customize.php?autofocus[section]=title_tagline' ), 'Customiser Site Identity' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );
	?>
</div>

<?php /* GROUP 3 */ ?>
<div class="wps-res-section">
	<div class="wps-res-section-header"><h2>Policy &amp; Settings Judgment</h2><span class="wps-res-section-count">9 diagnostics</span></div>
	<?php
	$s = 'registration-setting-intentional';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Open User Registration', $st );
	echo "<p class='wps-res-card__desc'>WordPress lets anyone create an account if Anyone can register is on. For most business sites this invites spam. Enable only if you run a membership site.</p>";
	echo "<div class='wps-res-current-state'><strong>Currently:</strong> " . ( $registration_open ? 'Open' : 'Closed' ) . '</div>';
	thisismyurl_shadow_rc_action_open( 'Update directly' );
	if ( $registration_open ) {
		thisismyurl_shadow_rc_option_row( 'users_can_register', 'Close registration', 'Open', '0', 'Turn Off Registration', $nonce );
	} else {
		thisismyurl_shadow_rc_option_row( 'users_can_register', 'Open registration (only if intentional)', 'Closed', '1', 'Enable Registration', $nonce );
	}
	thisismyurl_shadow_rc_admin_link( admin_url( 'options-general.php' ), 'Settings - General' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'xmlrpc-policy-intentional';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'XML-RPC Policy', $st );
	echo "<p class='wps-res-card__desc'>XML-RPC is a legacy API almost no modern site needs. Disable it unless you use Jetpack or the WordPress mobile app.</p>";
	thisismyurl_shadow_rc_action_open( 'Review your plugin stack' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'plugins.php' ), 'Plugins - Installed Plugins' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'search-enabled-intentional';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Site Search', $st );
	echo "<p class='wps-res-card__desc'>WordPress search creates thin-content result pages. For small brochure sites consider noindexing search results.</p>";
	thisismyurl_shadow_rc_action_open( 'Handle search result indexing' );
	$seo_link( $seo_titles_url, 'Search Page Noindex' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'auto-update-policy';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'WordPress Core Auto-Updates', $st );
	$core_display = match ( (string) $auto_update_core ) {
		'true', '1'  => 'All updates',
		'false', '0' => 'Disabled',
		default      => 'Minor updates only',
	};
	echo "<p class='wps-res-card__desc'>Minor updates contain security patches. Enabling them is strongly recommended. Major updates may need testing.</p>";
	echo "<div class='wps-res-current-state'><strong>Setting:</strong> " . esc_html( $core_display ) . '</div>';
	thisismyurl_shadow_rc_action_open( 'Change auto-update level' );
	thisismyurl_shadow_rc_option_row( 'wp_auto_update_core', 'Minor (security) updates only', $core_display, 'minor', 'Minor Updates Only', $nonce );
	thisismyurl_shadow_rc_option_row( 'wp_auto_update_core', 'Disable all auto-updates', $core_display, 'false', 'Disable Auto-Updates', $nonce );
	thisismyurl_shadow_rc_admin_link( admin_url( 'update-core.php' ), 'Dashboard - Updates' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'plugin-auto-updates';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Plugin Auto-Updates', $st );
	echo "<p class='wps-res-card__desc'>WordPress 5.5+ lets you enable auto-updates per plugin. Review each plugin and enable auto-updates where appropriate.</p>";
	thisismyurl_shadow_rc_action_open( 'Manage plugin auto-updates' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'plugins.php' ), 'Plugins - Installed Plugins' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'update-services-intentional';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Update Services (Blog Pings)', $st );
	echo "<p class='wps-res-card__desc'>WordPress pings blog aggregators every time you publish. For a site that rarely posts, this leaks site activity. Clear the field in Settings - Writing.</p>";
	echo "<div class='wps-res-current-state'><strong>Ping services:</strong> " . ( $ping_sites ? esc_html( mb_strimwidth( $ping_sites, 0, 80, '...' ) ) : 'None (already cleared)' ) . '</div>';
	thisismyurl_shadow_rc_action_open( 'Clear ping services' );
	if ( $ping_sites ) {
		thisismyurl_shadow_rc_option_row( 'ping_sites', 'Clear the Update Services list', 'Services configured', '', 'Clear Ping Services', $nonce );
	}
	thisismyurl_shadow_rc_admin_link( admin_url( 'options-writing.php' ), 'Settings - Writing' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'analytics-installed-intentional';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Analytics Tool', $st );
	echo "<p class='wps-res-card__desc'>Without analytics you will not know where visitors come from. Review your existing stack and choose the analytics approach that fits your site and privacy requirements.</p>";
	thisismyurl_shadow_rc_action_open( 'Review analytics tooling' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'plugins.php' ), 'Plugins - Installed Plugins' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'comment-policy-intentional';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Comment Policy', $st );
	echo "<p class='wps-res-card__desc'>WordPress ships with comments open and no moderation. Enable comment moderation, close comments by default on new posts.</p>";
	echo "<div class='wps-res-current-state'><strong>Comments:</strong> " . ( 'open' === $comment_status ? 'Open' : 'Closed' ) . " &bull; <strong>Moderation:</strong> " . ( $comment_mod ? 'On' : 'Off' ) . '</div>';
	thisismyurl_shadow_rc_action_open( 'Update comment settings' );
	if ( 'open' === $comment_status ) {
		thisismyurl_shadow_rc_option_row( 'default_comment_status', 'Close on new posts', 'Open', 'closed', 'Close Comments', $nonce );
	}
	if ( ! $comment_mod ) {
		thisismyurl_shadow_rc_option_row( 'comment_moderation', 'Require moderation', 'Off', '1', 'Enable Moderation', $nonce );
	}
	thisismyurl_shadow_rc_admin_link( admin_url( 'options-discussion.php' ), 'Settings - Discussion' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'rest-api-sensitive-routes-protected';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'REST API User List Exposed', $st );
	$ru = rest_url( 'wp/v2/users' );
	echo "<p class='wps-res-card__desc'>The REST API exposes your site usernames, used for brute-force attacks. Restrict the endpoint with your existing security tooling or a custom code change that requires authentication.</p>";
	echo "<div class='wps-res-current-state'><strong>Endpoint:</strong> <a href='" . esc_url( $ru ) . "' target='_blank' rel='noopener'>" . esc_html( $ru ) . " &#8599;</a></div>";
	thisismyurl_shadow_rc_action_open( 'Review your current protection approach' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'plugins.php' ), 'Plugins - Installed Plugins' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );
	?>
</div>

<?php /* GROUP 4 */ ?>
<div class="wps-res-section">
	<div class="wps-res-section-header"><h2>Required Pages</h2><span class="wps-res-section-count">5 diagnostics &mdash; every site should have these published and connected</span></div>
	<?php
	$s = 'about-page-published';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'About Page Published', $st );
	echo "<p class='wps-res-card__desc'>An About page is one of the highest-converting pages on any website. It explains who you are and why visitors should trust you.</p>";
	if ( $about_page ) {
		echo "<div class='wps-res-current-state'><strong>Found:</strong> <a href='" . esc_url( get_permalink( $about_page ) ) . "' target='_blank'>" . esc_html( $about_page->post_title ) . " &#8599;</a></div>";
	} else {
		echo "<div class='wps-res-current-state'><strong>No About page found.</strong></div>";
	}
	thisismyurl_shadow_rc_action_open( 'Create or review your About page' );
	if ( $about_page ) {
		thisismyurl_shadow_rc_admin_link( (string) get_edit_post_link( $about_page->ID ), 'Edit About Page' );
	} else {
		thisismyurl_shadow_rc_admin_link( admin_url( 'post-new.php?post_type=page' ), 'Create About Page' );
	}
	thisismyurl_shadow_rc_admin_link( admin_url( 'edit.php?post_type=page' ), 'All Pages' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'contact-page-published';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Contact Page Published', $st );
	echo "<p class='wps-res-card__desc'>A contact page is how potential customers, journalists, and collaborators reach you.</p>";
	if ( $contact_page ) {
		echo "<div class='wps-res-current-state'><strong>Found:</strong> <a href='" . esc_url( get_permalink( $contact_page ) ) . "' target='_blank'>" . esc_html( $contact_page->post_title ) . " &#8599;</a></div>";
	} else {
		echo "<div class='wps-res-current-state'><strong>No Contact page found.</strong></div>";
	}
	thisismyurl_shadow_rc_action_open( 'Create or review your Contact page' );
	if ( $contact_page ) {
		thisismyurl_shadow_rc_admin_link( (string) get_edit_post_link( $contact_page->ID ), 'Edit Contact Page' );
	} else {
		thisismyurl_shadow_rc_admin_link( admin_url( 'post-new.php?post_type=page' ), 'Create Contact Page' );
	}
	thisismyurl_shadow_rc_admin_link( admin_url( 'edit.php?post_type=page' ), 'All Pages' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'contact-page-has-form';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Contact Page Has a Form', $st );
	echo "<p class='wps-res-card__desc'>A plain email address forces visitors to open their mail app. A contact form lowers the barrier and protects your address from scrapers.</p>";
	thisismyurl_shadow_rc_action_open( 'Add a contact form' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'plugins.php' ), 'Plugins - Installed Plugins' );
	if ( $contact_page ) {
		thisismyurl_shadow_rc_admin_link( (string) get_edit_post_link( $contact_page->ID ), 'Edit Contact Page' );
	}
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'homepage-page-published';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Static Homepage Configured', $st );
	echo "<p class='wps-res-card__desc'>By default WordPress shows latest posts on the homepage. Most business sites need a custom static homepage selected in Settings - Reading.</p>";
	echo "<div class='wps-res-current-state'><strong>Homepage displays:</strong> ";
	if ( 'page' === $show_on_front && $homepage_id ) {
		$hp = get_post( $homepage_id );
		if ( $hp ) {
			echo "<a href='" . esc_url( (string) get_edit_post_link( $hp->ID ) ) . "'>" . esc_html( $hp->post_title ) . '</a>';
		} else {
			echo 'Page (ID not found)';
		}
	} elseif ( 'page' === $show_on_front ) {
		echo 'Static page (no page selected)';
	} else {
		echo 'Latest posts (WordPress default)';
	}
	echo '</div>';
	thisismyurl_shadow_rc_action_open( 'Configure Reading settings' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'options-reading.php' ), 'Settings - Reading' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'edit.php?post_type=page' ), 'All Pages' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );

	$s = 'posts-page-published';
	$st = thisismyurl_shadow_rc_status( $s, $findings, $excluded, $records );
	thisismyurl_shadow_rc_card_open( $s, 'Blog / Posts Page Configured', $st );
	echo "<p class='wps-res-card__desc'>If your site has a static homepage and a blog, WordPress needs to know which page the posts listing lives on.</p>";
	echo "<div class='wps-res-current-state'><strong>Posts page:</strong> ";
	if ( $posts_page_id ) {
		$pp = get_post( $posts_page_id );
		if ( $pp ) {
			echo "<a href='" . esc_url( (string) get_edit_post_link( $pp->ID ) ) . "'>" . esc_html( $pp->post_title ) . '</a>';
		} else {
			echo 'Set (page not found)';
		}
	} else {
		echo 'Not set';
	}
	echo '</div>';
	thisismyurl_shadow_rc_action_open( 'Configure the posts page' );
	thisismyurl_shadow_rc_admin_link( admin_url( 'options-reading.php' ), 'Settings - Reading' );
	thisismyurl_shadow_rc_action_close();
	thisismyurl_shadow_rc_card_close( $s, $st, $nonce );
	?>
</div>

</div><!-- /.wps-resolution-wrap -->

<script>
(function() {
"use strict";

var ajaxUrl = <?php echo wp_json_encode( admin_url( "admin-ajax.php" ) ); ?>;

document.querySelectorAll(".wps-res-card__header").forEach(function(h) {
	function toggle() {
		var b = document.getElementById(h.getAttribute("aria-controls"));
		if (!b) {
			return;
		}

		var isOpen = b.hasAttribute("hidden");
		b.toggleAttribute("hidden", !isOpen);
		h.setAttribute("aria-expanded", isOpen ? "true" : "false");
	}

	h.addEventListener("click", toggle);
	h.addEventListener("keydown", function(e) {
		if (e.key === "Enter" || e.key === " ") {
			e.preventDefault();
			toggle();
		}
	});
});

document.querySelectorAll(".wps-rc-btn").forEach(function(btn) {
	btn.addEventListener("click", function() {
		var card = btn.closest(".wps-res-card");
		var msg = card ? card.querySelector(".wps-res-feedback-msg") : null;
		btn.disabled = true;

		var fd = new FormData();
		fd.append("action", "thisismyurl_shadow_resolution_save");
		fd.append("nonce", btn.dataset.nonce);
		fd.append("diagnostic_slug", btn.dataset.slug);
		fd.append("status", btn.dataset.action);

		fetch(ajaxUrl, { method: "POST", body: fd })
			.then(function(r) { return r.json(); })
			.then(function(d) {
				btn.disabled = false;
				if (d.success) {
					if (msg) {
						msg.textContent = d.data.message || <?php echo wp_json_encode( __( "Saved.", "thisismyurl-shadow" ) ); ?>;
						msg.style.display = "inline";
					}
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					alert((d.data && d.data.message) || <?php echo wp_json_encode( __( "Could not save.", "thisismyurl-shadow" ) ); ?>);
				}
			})
			.catch(function() {
				btn.disabled = false;
				alert(<?php echo wp_json_encode( __( "Network error.", "thisismyurl-shadow" ) ); ?>);
			});
	});
});

document.querySelectorAll(".wps-rc-save-option").forEach(function(btn) {
	btn.addEventListener("click", function() {
		var row = btn.closest(".wps-res-option-row");
		var fb = row ? row.querySelector(".wps-res-option-feedback") : null;
		btn.disabled = true;
		btn.textContent = <?php echo wp_json_encode( __( "Saving...", "thisismyurl-shadow" ) ); ?>;

		var fd = new FormData();
		fd.append("action", "thisismyurl_shadow_resolution_update_option");
		fd.append("nonce", btn.dataset.nonce);
		fd.append("option_name", btn.dataset.option);
		fd.append("option_value", btn.dataset.value);

		fetch(ajaxUrl, { method: "POST", body: fd })
			.then(function(r) { return r.json(); })
			.then(function(d) {
				btn.disabled = false;
				if (d.success) {
					btn.textContent = <?php echo wp_json_encode( __( "Saved", "thisismyurl-shadow" ) ); ?>;
					if (fb) {
						fb.textContent = d.data.message || <?php echo wp_json_encode( __( "Updated.", "thisismyurl-shadow" ) ); ?>;
						fb.style.display = "inline";
					}
					setTimeout(function() { location.reload(); }, 1400);
				} else {
					btn.textContent = <?php echo wp_json_encode( __( "Save", "thisismyurl-shadow" ) ); ?>;
					if (fb) {
						fb.textContent = (d.data && d.data.message) || <?php echo wp_json_encode( __( "Could not save.", "thisismyurl-shadow" ) ); ?>;
						fb.classList.add("error");
						fb.style.display = "inline";
					}
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = <?php echo wp_json_encode( __( "Save", "thisismyurl-shadow" ) ); ?>;
				if (fb) {
					fb.textContent = <?php echo wp_json_encode( __( "Network error.", "thisismyurl-shadow" ) ); ?>;
					fb.classList.add("error");
					fb.style.display = "inline";
				}
			});
	});
});
})();
</script>
<?php
} // end thisismyurl_shadow_render_resolution_page()
