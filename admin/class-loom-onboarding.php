<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Loom_Onboarding  -  New user onboarding flow.
 *
 * Currently handled inline by the dashboard: when loom_scan_completed is false,
 * the dashboard renders a hero CTA prompting the user to run their first scan.
 * This class is a placeholder for future dedicated onboarding wizard.
 */
class Loom_Onboarding {

	/**
	 * Initialize onboarding hooks (currently no-op).
	 */
	public static function init() {
		// Future: redirect on activation, multi-step wizard, etc.
	}
}
