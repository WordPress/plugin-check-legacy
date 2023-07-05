<?php
namespace WordPressdotorg\Plugin_Check\Checks;
use WordPressdotorg\Plugin_Check\Guideline_Violation;

class Trademarks extends Check_Base {

	// Tradmarked terms that are commonly abused on WordPress.org.
	const TRADEMARKED_SLUGS = array(
		'adobe-',
		'adsense-',
		'advanced-custom-fields-',
		'adwords-',
		'akismet-',
		'all-in-one-wp-migration',
		'amazon-',
		'android-',
		'apple-',
		'applenews-',
		'applepay-',
		'aws-',
		'azon-',
		'bbpress-',
		'bing-',
		'booking-com',
		'bootstrap-',
		'buddypress-',
		'chatgpt-',
		'chat-gpt-',
		'cloudflare-',
		'contact-form-7-',
		'cpanel-',
		'disqus-',
		'divi-',
		'dropbox-',
		'easy-digital-downloads-',
		'elementor-',
		'envato-',
		'fbook',
		'facebook',
		'fb-',
		'fb-messenger',
		'fedex-',
		'feedburner',
		'firefox-',
		'fontawesome-',
		'font-awesome-',
		'ganalytics-',
		'gberg',
		'github-',
		'givewp-',
		'google-',
		'googlebot-',
		'googles-',
		'gravity-form-',
		'gravity-forms-',
		'gravityforms-',
		'gtmetrix-',
		'gutenberg',
		'guten-',
		'hubspot-',
		'ig-',
		'insta-',
		'instagram',
		'internet-explorer-',
		'ios-',
		'jetpack-',
		'macintosh-',
		'macos-',
		'mailchimp-',
		'microsoft-',
		'ninja-forms-',
		'oculus',
		'onlyfans-',
		'only-fans-',
		'opera-',
		'paddle-',
		'paypal-',
		'pinterest-',
		'plugin',
		'skype-',
		'stripe-',
		'tiktok-',
		'tik-tok-',
		'trustpilot',
		'twitch-',
		'twitter-',
		'tweet',
		'ups-',
		'usps-',
		'vvhatsapp',
		'vvcommerce',
		'vva-',
		'vvoo',
		'wa-',
		'webpush-vn',
		'wh4tsapps',
		'whatsapp',
		'whats-app',
		'watson',
		'windows-',
		'wocommerce',
		'woocom-',
		'woocommerce',  // technically ending with '-for-woocommerce' is allowed.
		'woocomerce',
		'woo-commerce',
		'woo-',
		'wo-',
		'wordpress',
		'wordpess',
		'wpress',
		'wp-',
		'wp-mail-smtp-',
		'yandex-',
		'yahoo-',
		'yoast',
		'youtube-',
		'you-tube-',
	);

	// Domains from which exceptions would be accepted.
	const TRADEMARK_EXCEPTIONS = array(
		'adobe.com'             => array( 'adobe' ),
		'automattic.com'        => array( 'akismet', 'akismet-', 'jetpack', 'jetpack-', 'wordpress', 'wp-', 'woo', 'woo-', 'woocommerce', 'woocommerce-' ),
		'facebook.com'          => array( 'facebook', 'instagram', 'oculus', 'whatsapp' ),
		'support.microsoft.com' => array( 'bing-', 'microsoft-' ),
		'trustpilot.com'        => array( 'trustpilot' ),
		'microsoft.com'         => array( 'bing-', 'microsoft-' ),
		'yandex-team.ru'        => array( 'yandex' ),
		'yoast.com'             => array( 'yoast' ),
		'opera.com'             => array( 'opera-' ),
		'adobe.com'             => array( 'adobe-' ),
	);

	// Trademarks that are allowed as 'for-whatever' ONLY.
	const FOR_USE_EXCEPTIONS = array(
		'woocommerce',
	);

	// Commonly used 'combo' names (to prevent things like 'woopress').
	const PORTMANTEAUS = array(
		'woo',
	);

	public function check_readme() {
		$preamble = __( 'Error: The readme name includes a restricted term.', 'wporg-plugins' );

		return $this->_trademark_check( $this->readme->name ?? false, $preamble );
	}

	public function check_plugin_name() {
		$preamble = __( 'Error: The plugin name includes a restricted term.', 'wporg-plugins' );

		return $this->_trademark_check( $this->headers['Name'] ?? false, $preamble );
	}

	public function check_plugin_slug() {
		$preamble = __( 'Error: The plugin slug includes a restricted term.', 'wporg-plugins' );

		return $this->_trademark_check( $this->slug ?? false, $preamble );
	}

	public function _trademark_check( $input, $preamble = '' ) {
		if ( empty( $input ) ) {
			return;
		}

		/*
		 * Get the user email domain.
		 * For plugins on WordPress.org, the WP_Post on the plugin directory will be passed.
		 */
		$user_email_domain = false;
		if ( $this->post ) {
			$user_email_domain = explode( '@', get_user_by( 'id', $this->post->post_author )->user_email, 2 );
		}

		$check = $this->has_trademarked_slug( $input, $user_email_domain );
		if ( ! $check ) {
			return;
		}

		if ( $check === trim( $check, '-' ) && in_array( $check, self::FOR_USE_EXCEPTIONS ) ) {
			// Trademarks that do NOT end in "-", but are within the FOR_USE_EXCEPTIONS array can be used, but only if it ends with 'for x'
			$message = sprintf(
				/* translators: 1: plugin slug, 2: trademarked term */
				__( 'Your chosen plugin name - %1$s - contains the restricted term "%2$s" which cannot be used within in your plugin name, unless your plugin name ends with "for %2$s". The term must still not appear anywhere else in your name.', 'wporg-plugins' ),
				'<code>' . esc_html( $input ) . '</code>',
				esc_html( trim( $check, '-' ) )
			);
		} elseif ( $check === trim( $check, '-' ) ) {
			// Trademarks that do NOT end in "-" indicate slug cannot contain term at all.
			$message = sprintf(
				/* translators: 1: plugin slug, 2: trademarked term */
				__( 'Your chosen plugin name - %1$s - contains the restricted term "%2$s" which cannot be used at all in your plugin name.', 'wporg-plugins' ),
				'<code>' . esc_html( $input ) . '</code>',
				esc_html( trim( $check, '-' ) )
			);
		} else {
			// Trademarks ending in "-" indicate slug cannot BEGIN with that term.
			$message = sprintf(
				/* translators: 1: plugin slug, 2: trademarked term  */
				__( 'Your chosen plugin name - %1$s - contains the restricted term "%2$s" and cannot be used to begin your plugin name. We disallow the use of certain terms in ways that are abused, or potentially infringe on and/or are misleading with regards to trademarks. You may use the term "%2$s" elsewhere in your plugin name, such as "... for %2$s".', 'wporg-plugins' ),
				'<code>' . esc_html( $input ) . '</code>',
				esc_html( trim( $check, '-' ) )
			);
		}

		return new Guideline_Violation( 'trademarked_term', $preamble . ' ' . $message, $check );
	}

	/**
	 * Whether the uploaded plugin uses a trademark in the slug.
	 *
	 * @return string|false The trademarked slug if found, false otherwise.
	 */
	public function has_trademarked_slug( $slug, $email_domain_exceptions = '' ) {
		// We work on slugs for this check.
		$slug = sanitize_title_with_dashes( $slug );

		$has_trademarked_slug = false;

		foreach ( self::TRADEMARKED_SLUGS as $trademark ) {
			if ( '-' === $trademark[-1] ) {
				// Trademarks ending in "-" indicate slug cannot begin with that term.
				if ( 0 === strpos( $slug, $trademark ) ) {
					$has_trademarked_slug = $trademark;
					break;
				}
			} elseif ( false !== strpos( $slug, $trademark ) ) {
				// Otherwise, the term cannot appear anywhere in slug.

				// check for 'for-TRADEMARK' exceptions.
				if ( $this->is_valid_for_use_exception( $slug, $trademark ) ) {
					// It is a valid for-use exception, try the next trademark.
					continue;
				}

				$has_trademarked_slug = $trademark;
				break;
			}
		}

		// Check portmanteaus.
		if ( ! $has_trademarked_slug ) {
			foreach ( self::PORTMANTEAUS as $portmanteau ) {
				if ( 0 === stripos( $slug, $portmanteau ) ) {
					$has_trademarked_slug = $portmanteau;
					break;
				}
			}
		}

		if ( $email_domain_exceptions ) {
			// If email domain is on our list of possible exceptions, we have an extra check.
			if ( $has_trademarked_slug && array_key_exists( $email_domain_exceptions, self::TRADEMARK_EXCEPTIONS ) ) {
				// If $has_trademarked_slug is in the array for that domain, they can use the term.
				if ( in_array( $has_trademarked_slug, self::TRADEMARK_EXCEPTIONS[ $email_domain_exceptions ] ) ) {
					$has_trademarked_slug = false;
				}
			}
		}

		return $has_trademarked_slug;
	}

	/**
	 * Validate whether the trademark is valid with a for-use exception.
	 */
	public function is_valid_for_use_exception( $slug, $trademark ) {
		if ( ! $slug || ! $trademark || ! in_array( $trademark, self::FOR_USE_EXCEPTIONS ) ) {
			return false;
		}

		$for_trademark = '-for-' . $trademark;
		if ( ! str_ends_with( $slug, $for_trademark ) ) {
			// The slug doesn't end with 'for-TRADEMARK', so it's an invalid use.
			return false;
		}

		// Yes the slug ENDS with 'for-TRADEMARK'.
		// Validate that the term still doesn't appear in another position of the slug.
		$short_slug = substr( $slug, 0, -1 * strlen( $for_trademark ) );

		// If the trademark still doesn't exist in the slug, it's OK.
		return false === strpos( $short_slug, $trademark );
	}

}
