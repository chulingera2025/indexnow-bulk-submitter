<?php

class IndexNow_Bulk_Submitter_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_indexnow_bulk_submit', array( $this, 'ajax_bulk_submit' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'indexnow',
			__( '批量提交', 'indexnow-bulk-submitter' ),
			__( '批量提交', 'indexnow-bulk-submitter' ),
			'manage_options',
			'indexnow-bulk-submitter',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_scripts( $hook ) {
		if ( 'indexnow_page_indexnow-bulk-submitter' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'indexnow-bulk-submitter-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/admin.css',
			array(),
			INDEXNOW_BULK_SUBMITTER_VERSION
		);

		wp_enqueue_script(
			'indexnow-bulk-submitter-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/admin.js',
			array( 'jquery' ),
			INDEXNOW_BULK_SUBMITTER_VERSION,
			true
		);

		wp_localize_script( 'indexnow-bulk-submitter-admin', 'indexnowBulkSubmitter', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'indexnow_bulk_submit' ),
			'strings' => array(
				'parsing' => __( '正在解析sitemap...', 'indexnow-bulk-submitter' ),
				'submitting' => __( '正在提交URL...', 'indexnow-bulk-submitter' ),
				'completed' => __( '批量提交完成！', 'indexnow-bulk-submitter' ),
				'error' => __( '发生错误', 'indexnow-bulk-submitter' ),
			)
		) );
	}

	public function render_admin_page() {
		$default_sitemap = IndexNow_Sitemap_Parser::get_default_sitemap_url();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="card">
				<h2>批量提交Sitemap中的URL到IndexNow</h2>
				<p>此工具会解析你的sitemap并将所有URL批量提交到IndexNow，适合提交安装IndexNow插件之前发布的历史文章。</p>

				<form id="indexnow-bulk-submit-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="sitemap_url">Sitemap URL</label>
							</th>
							<td>
								<input type="url"
									   id="sitemap_url"
									   name="sitemap_url"
									   value="<?php echo esc_attr( $default_sitemap ); ?>"
									   class="regular-text"
									   required>
								<p class="description">输入你的sitemap地址，支持sitemap索引文件</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="batch_size">批次大小</label>
							</th>
							<td>
								<input type="number"
									   id="batch_size"
									   name="batch_size"
									   value="100"
									   min="1"
									   max="10000"
									   class="small-text">
								<p class="description">每批提交的URL数量（IndexNow API单次最多支持10,000个）</p>
							</td>
						</tr>
					</table>

					<?php submit_button( '开始批量提交', 'primary', 'submit', false ); ?>
				</form>
			</div>

			<div id="indexnow-progress" style="display:none;">
				<h3>提交进度</h3>
				<div class="progress-bar">
					<div class="progress-bar-fill" style="width: 0%"></div>
				</div>
				<p class="progress-text">准备中...</p>
				<div class="progress-stats">
					<span>总计: <strong id="total-urls">0</strong></span> |
					<span>成功: <strong id="success-count">0</strong></span> |
					<span>失败: <strong id="error-count">0</strong></span>
				</div>
			</div>

			<div id="indexnow-results" style="display:none;">
				<h3>提交结果</h3>
				<div class="results-content"></div>
			</div>
		</div>
		<?php
	}

	public function ajax_bulk_submit() {
		check_ajax_referer( 'indexnow_bulk_submit', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}

		$sitemap_url = isset( $_POST['sitemap_url'] ) ? esc_url_raw( $_POST['sitemap_url'] ) : '';
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;
		$batch_index = isset( $_POST['batch_index'] ) ? intval( $_POST['batch_index'] ) : 0;
		$session_key = isset( $_POST['session_key'] ) ? sanitize_text_field( $_POST['session_key'] ) : '';

		if ( empty( $sitemap_url ) ) {
			wp_send_json_error( array( 'message' => 'Sitemap URL不能为空' ) );
		}

		$parsed_url = parse_url( $sitemap_url );
		if ( ! isset( $parsed_url['scheme'] ) || ! in_array( $parsed_url['scheme'], array( 'http', 'https' ), true ) ) {
			wp_send_json_error( array( 'message' => '仅支持HTTP(S)协议的Sitemap URL' ) );
		}

		if ( $batch_size < 1 || $batch_size > 10000 ) {
			wp_send_json_error( array( 'message' => '批次大小必须在1-10000之间' ) );
		}

		if ( $batch_index < 0 ) {
			wp_send_json_error( array( 'message' => '无效的批次索引' ) );
		}

		if ( empty( $session_key ) ) {
			set_time_limit( 300 );

			$parser = new IndexNow_Sitemap_Parser();
			if ( ! $parser->parse_sitemap( $sitemap_url ) ) {
				wp_send_json_error( array(
					'message' => '解析sitemap失败',
					'errors' => $parser->get_errors()
				) );
			}

			$urls = $parser->get_urls();
			if ( empty( $urls ) ) {
				wp_send_json_error( array( 'message' => '未找到可提交的URL' ) );
			}

			$session_key = 'indexnow_bulk_' . get_current_user_id() . '_' . wp_generate_password( 12, false );
			set_transient( $session_key, array(
				'urls' => $urls,
				'timestamp' => time(),
				'user_id' => get_current_user_id()
			), 3600 );

			wp_send_json_success( array(
				'type' => 'parse',
				'session_key' => $session_key,
				'total_urls' => count( $urls ),
				'total_batches' => ceil( count( $urls ) / $batch_size ),
				'message' => '解析完成，找到 ' . count( $urls ) . ' 个URL'
			) );
		}

		$rate_limit_key = 'indexnow_rate_limit_' . get_current_user_id();
		$last_request = get_transient( $rate_limit_key );

		if ( false !== $last_request ) {
			$time_diff = time() - $last_request;
			if ( $time_diff < 1 ) {
				wp_send_json_error( array(
					'message' => '请求过于频繁，请稍后再试'
				) );
			}
		}

		set_transient( $rate_limit_key, time(), 60 );

		$session_data = get_transient( $session_key );
		if ( false === $session_data || ! isset( $session_data['user_id'] ) || $session_data['user_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => '会话已过期或无效，请重新开始' ) );
		}

		$urls = $session_data['urls'];

		$batch_urls = array_slice( $urls, $batch_index * $batch_size, $batch_size );
		if ( empty( $batch_urls ) ) {
			delete_transient( $session_key );
			wp_send_json_success( array(
				'completed' => true,
				'message' => '所有URL提交完成'
			) );
		}

		$result = $this->submit_urls_to_indexnow( $batch_urls );

		wp_send_json_success( array(
			'batch_index' => $batch_index + 1,
			'submitted_count' => count( $batch_urls ),
			'success' => $result['success'],
			'message' => $result['message']
		) );
	}

	private function submit_urls_to_indexnow( $urls ) {
		$api_key_encoded = get_option( 'indexnow-admin_api_key' );
		$is_valid_api_key = get_option( 'indexnow-is_valid_api_key' );

		if ( ! $is_valid_api_key || '1' !== $is_valid_api_key ) {
			return array(
				'success' => false,
				'message' => 'IndexNow API密钥未配置或无效'
			);
		}

		$api_key = base64_decode( $api_key_encoded );
		$site_url = get_home_url();
		$site_host = str_replace( array( 'http://', 'https://' ), '', $site_url );
		$site_host = rtrim( $site_host, '/' );

		$data = json_encode( array(
			'host' => $site_host,
			'key' => $api_key,
			'keyLocation' => trailingslashit( $site_url ) . $api_key . '.txt',
			'urlList' => $urls,
		) );

		$response = wp_remote_post( 'https://api.indexnow.org/indexnow/', array(
			'body' => $data,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => '提交失败: ' . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code || 202 === $code ) {
			return array(
				'success' => true,
				'message' => '提交成功 ' . count( $urls ) . ' 个URL'
			);
		}

		return array(
			'success' => false,
			'message' => '提交失败，HTTP状态码: ' . $code
		);
	}
}