<?php

namespace WLLP\App\Helpers;

use DateTime;
use DateTimeZone;

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

	public static function beforeDisplayDate( $date, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format', 'Y-m-d H:i:s' );
		}
		if ( empty( $date ) ) {
			return null;
		}
		if ( (int) $date != $date ) {
			return $date;
		}

		$converted_time = self::convert_utc_to_wp_time( gmdate( 'Y-m-d H:i:s', $date ), $format );
		if ( apply_filters( 'wllp_translate_display_date', true ) ) {
			$datetime = DateTime::createFromFormat( $format, $converted_time );
			if ( $datetime !== false ) {
				$time = $datetime->getTimestamp();
			} else {
				$time = strtotime( $converted_time );
			}
			$converted_time = date_i18n( $format, $time );
		}

		return $converted_time;
	}

	private static function convert_utc_to_wp_time( $datetime, $format = 'Y-m-d H:i:s', $modify = '' ) {
		try {
			$timezone     = new DateTimeZone( 'UTC' );
			$current_time = new DateTime( $datetime, $timezone );
			if ( ! empty( $modify ) ) {
				$current_time->modify( $modify );
			}
			$wp_time_zone = new DateTimeZone( self::get_wp_time_zone() );
			$current_time->setTimezone( $wp_time_zone );
			$converted_time = $current_time->format( $format );
		}
		catch ( \Exception $e ) {
			$converted_time = $datetime;
		}

		return $converted_time;
	}

	private static function get_wp_time_zone() {
		if ( ! function_exists( 'wp_timezone_string' ) ) {
			$timezone_string = get_option( 'timezone_string' );
			if ( $timezone_string ) {
				return $timezone_string;
			}
			$offset    = (float) get_option( 'gmt_offset' );
			$hours     = (int) $offset;
			$minutes   = ( $offset - $hours );
			$sign      = ( $offset < 0 ) ? '-' : '+';
			$abs_hour  = abs( $hours );
			$abs_mins  = abs( $minutes * 60 );
			$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

			return $tz_offset;
		}

		return wp_timezone_string();
	}
}