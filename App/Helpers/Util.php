<?php

namespace WLLP\App\Helpers;

class Util {
	/**
	 * render template.
	 *
	 * @param   string  $file     File path.
	 * @param   array   $data     Template data.
	 * @param   bool    $display  Display or not.
	 *
	 * @return string|void
	 */
	public static function renderTemplate( string $file, array $data = [], bool $display = true ) {
		$content = '';
		if ( file_exists( $file ) ) {
			ob_start();
			extract( $data );
			include $file;
			$content = ob_get_clean();
		}
		if ( $display ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $content;
		} else {
			return $content;
		}
	}

	/**
	 * Get default values.
	 *
	 * @param   string|null  $key  Key name.
	 *
	 * @return mixed
	 */
	public static function getDefaults( $key = null ) {
		$defaults = apply_filters( 'wllp_options_default_values', [
			'grace_period_enabled'          => 0,
			'grace_period_days'             => 30,
			'levels_from_which_point_based' => 'from_total_earned_points',
			'level_metadata'                => [],
			'wllp_settings_data'            => [],
		] );

		if ( $key == null ) {
			return $defaults;
		}

		return $defaults[ $key ] ?? null;
	}
}