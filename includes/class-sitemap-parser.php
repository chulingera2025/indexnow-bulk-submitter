<?php

class IndexNow_Sitemap_Parser {

	private $urls = array();
	private $errors = array();
	private $max_urls = 50000;
	private $max_sitemap_size = 52428800;
	private $max_depth = 3;
	private $current_depth = 0;

	public function parse_sitemap( $sitemap_url ) {
		if ( 0 === $this->current_depth ) {
			$this->urls = array();
			$this->errors = array();
		}

		if ( ! $this->is_allowed_url( $sitemap_url ) ) {
			$this->errors[] = '不允许访问此URL';
			return false;
		}

		$response = wp_remote_get( $sitemap_url, array(
			'timeout' => 30,
			'sslverify' => true,
			'redirection' => 3,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		) );

		if ( is_wp_error( $response ) ) {
			$this->errors[] = 'Sitemap请求失败: ' . $response->get_error_message();
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			$this->errors[] = 'Sitemap内容为空';
			return false;
		}

		if ( strlen( $body ) > $this->max_sitemap_size ) {
			$this->errors[] = 'Sitemap文件过大 (最大50MB)';
			return false;
		}

		$old_value = libxml_disable_entity_loader( true );
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET );

		if ( false === $xml ) {
			$xml_errors = libxml_get_errors();
			$error_messages = array();
			foreach ( $xml_errors as $error ) {
				$error_messages[] = trim( $error->message );
			}
			libxml_clear_errors();

			$this->errors[] = 'Sitemap XML解析失败: ' . implode( '; ', $error_messages );
			libxml_disable_entity_loader( $old_value );
			return false;
		}

		libxml_disable_entity_loader( $old_value );

		if ( isset( $xml->sitemap ) ) {
			return $this->parse_sitemap_index( $xml );
		} elseif ( isset( $xml->url ) ) {
			return $this->parse_urlset( $xml );
		} else {
			$this->errors[] = '无法识别的sitemap格式';
			return false;
		}
	}

	private function parse_sitemap_index( $xml ) {
		if ( $this->current_depth >= $this->max_depth ) {
			$this->errors[] = 'Sitemap嵌套层级过深（最大' . $this->max_depth . '层）';
			return false;
		}

		$this->current_depth++;

		foreach ( $xml->sitemap as $sitemap ) {
			$loc = (string) $sitemap->loc;
			if ( ! empty( $loc ) ) {
				$this->parse_sitemap( $loc );
			}

			if ( count( $this->urls ) >= $this->max_urls ) {
				$this->errors[] = '已达到最大URL数量限制 (' . $this->max_urls . ')';
				$this->current_depth--;
				return false;
			}
		}

		$this->current_depth--;
		return true;
	}

	private function parse_urlset( $xml ) {
		foreach ( $xml->url as $url ) {
			if ( count( $this->urls ) >= $this->max_urls ) {
				$this->errors[] = '已达到最大URL数量限制 (' . $this->max_urls . ')';
				return false;
			}

			$loc = (string) $url->loc;
			if ( ! empty( $loc ) && $this->is_valid_url( $loc ) ) {
				$this->urls[] = $loc;
			}
		}
		return true;
	}

	private function is_valid_url( $url ) {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return false;
		}

		$parsed = parse_url( $url );
		if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return false;
		}

		return true;
	}

	private function is_allowed_url( $url ) {
		$parsed = parse_url( $url );
		if ( ! isset( $parsed['host'] ) ) {
			return false;
		}

		if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			return false;
		}

		$url_host = strtolower( $parsed['host'] );

		if ( preg_match( '/^(127\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|169\.254\.)/', $url_host ) ) {
			return false;
		}

		if ( in_array( $url_host, array( 'localhost', '0.0.0.0', '::1' ), true ) ) {
			return false;
		}

		$site_url = get_home_url();
		$site_host = strtolower( parse_url( $site_url, PHP_URL_HOST ) );
		$site_host_www = 'www.' . $site_host;
		$site_host_no_www = preg_replace( '/^www\./', '', $site_host );

		$allowed_hosts = array( $site_host, $site_host_www, $site_host_no_www );
		$allowed_hosts = array_unique( array_filter( $allowed_hosts ) );

		return in_array( $url_host, $allowed_hosts, true );
	}

	public function get_urls() {
		return array_unique( $this->urls );
	}

	public function get_errors() {
		return $this->errors;
	}

	public function get_url_count() {
		return count( $this->get_urls() );
	}

	public static function get_default_sitemap_url() {
		$site_url = get_home_url();

		$possible_sitemaps = array(
			$site_url . '/wp-sitemap.xml',
			$site_url . '/sitemap.xml',
			$site_url . '/sitemap_index.xml',
		);

		foreach ( $possible_sitemaps as $sitemap_url ) {
			$response = wp_remote_head( $sitemap_url );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				return $sitemap_url;
			}
		}

		return $site_url . '/wp-sitemap.xml';
	}
}