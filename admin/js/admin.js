jQuery(document).ready(function($) {
	'use strict';

	let totalUrls = 0;
	let totalBatches = 0;
	let currentBatch = 0;
	let successCount = 0;
	let errorCount = 0;
	let sessionKey = '';

	$('#indexnow-bulk-submit-form').on('submit', function(e) {
		e.preventDefault();

		const sitemapUrl = $('#sitemap_url').val();
		const batchSize = parseInt($('#batch_size').val());

		if (!sitemapUrl) {
			alert('请输入Sitemap URL');
			return;
		}

		resetProgress();
		showProgress();
		$(this).addClass('submitting');

		parseSitemap(sitemapUrl, batchSize);
	});

	function parseSitemap(sitemapUrl, batchSize) {
		updateProgressText(indexnowBulkSubmitter.strings.parsing);

		$.ajax({
			url: indexnowBulkSubmitter.ajaxUrl,
			type: 'POST',
			data: {
				action: 'indexnow_bulk_submit',
				nonce: indexnowBulkSubmitter.nonce,
				sitemap_url: sitemapUrl,
				batch_size: batchSize,
				batch_index: 0
			},
			success: function(response) {
				if (response.success) {
					totalUrls = response.data.total_urls;
					totalBatches = response.data.total_batches;
					sessionKey = response.data.session_key;
					$('#total-urls').text(totalUrls);

					addResult('success', response.data.message);

					if (totalUrls > 0) {
						submitBatch(sitemapUrl, batchSize, 0);
					} else {
						updateProgressText('未找到可提交的URL');
						$('#indexnow-bulk-submit-form').removeClass('submitting');
					}
				} else {
					handleError(response.data.message);
				}
			},
			error: function() {
				handleError('解析sitemap时发生网络错误');
			}
		});
	}

	function submitBatch(sitemapUrl, batchSize, batchIndex) {
		currentBatch = batchIndex + 1;
		updateProgressText(indexnowBulkSubmitter.strings.submitting + ' (批次 ' + currentBatch + '/' + totalBatches + ')');
		updateProgressBar();

		$.ajax({
			url: indexnowBulkSubmitter.ajaxUrl,
			type: 'POST',
			data: {
				action: 'indexnow_bulk_submit',
				nonce: indexnowBulkSubmitter.nonce,
				sitemap_url: sitemapUrl,
				batch_size: batchSize,
				batch_index: batchIndex,
				session_key: sessionKey
			},
			success: function(response) {
				if (response.success) {
					if (response.data.completed) {
						handleCompletion();
					} else if (typeof response.data.batch_index !== 'undefined') {
						if (response.data.success) {
							successCount += response.data.submitted_count;
							addResult('success', '批次 ' + currentBatch + ': ' + response.data.message);
						} else {
							errorCount += response.data.submitted_count;
							addResult('error', '批次 ' + currentBatch + ': ' + response.data.message);
						}

						$('#success-count').text(successCount);
						$('#error-count').text(errorCount);

						submitBatch(sitemapUrl, batchSize, response.data.batch_index);
					} else {
						handleError('收到意外的响应格式');
					}
				} else {
					handleError(response.data.message);
				}
			},
			error: function() {
				handleError('提交批次 ' + currentBatch + ' 时发生网络错误');
			}
		});
	}

	function handleCompletion() {
		updateProgressBar(100);
		updateProgressText(indexnowBulkSubmitter.strings.completed);
		addResult('success', '总计提交: ' + totalUrls + ' 个URL，成功: ' + successCount + '，失败: ' + errorCount);
		$('#indexnow-bulk-submit-form').removeClass('submitting');
	}

	function handleError(message) {
		addResult('error', indexnowBulkSubmitter.strings.error + ': ' + message);
		$('#indexnow-bulk-submit-form').removeClass('submitting');
		updateProgressText('提交中断');
	}

	function showProgress() {
		$('#indexnow-progress').show();
		$('#indexnow-results').show();
	}

	function resetProgress() {
		totalUrls = 0;
		totalBatches = 0;
		currentBatch = 0;
		successCount = 0;
		errorCount = 0;
		sessionKey = '';

		$('#total-urls').text('0');
		$('#success-count').text('0');
		$('#error-count').text('0');
		$('.progress-bar-fill').css('width', '0%');
		$('.results-content').empty();
	}

	function updateProgressBar(percentage) {
		if (percentage === undefined) {
			percentage = totalBatches > 0 ? (currentBatch / totalBatches * 100) : 0;
		}
		$('.progress-bar-fill').css('width', percentage + '%');
	}

	function updateProgressText(text) {
		$('.progress-text').text(text);
	}

	function addResult(type, message) {
		const validTypes = ['success', 'error', 'info', 'warning'];
		if (!validTypes.includes(type)) {
			type = 'info';
		}

		const $result = $('<div>')
			.addClass('result-' + type)
			.text('[' + getCurrentTime() + '] ' + message);
		$('.results-content').append($result);
		$('.results-content').scrollTop($('.results-content')[0].scrollHeight);
	}

	function getCurrentTime() {
		const now = new Date();
		return now.getHours().toString().padStart(2, '0') + ':' +
			   now.getMinutes().toString().padStart(2, '0') + ':' +
			   now.getSeconds().toString().padStart(2, '0');
	}
});