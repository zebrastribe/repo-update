(function ($) {
	'use strict';

	function showMessage(target, type, message) {
		target.empty();
		target.append(
			$('<div>', { 'class': 'notice notice-' + type })
				.append($('<p>').text(message))
		);
	}

	$(document).on('click', '.repo-update-confirm', function (event) {
		var message = $(this).data('message') || repoUpdate.i18n.confirmDelete;
		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});

	$('#repo-update-fetch-branches').on('click', function () {
		var $button = $(this);
		var $branch = $('#branch');
		var $message = $('#repo-update-form-message');

		$button.prop('disabled', true).text(repoUpdate.i18n.loading);

		$.post(repoUpdate.ajaxUrl, {
			action: 'repo_update_fetch_branches',
			nonce: repoUpdate.nonce,
			owner: $('#owner').val(),
			name: $('#name').val(),
			token: $('#token').val(),
			id: $('input[name="id"]').val()
		})
			.done(function (response) {
				if (!response.success) {
					showMessage($message, 'error', response.data && response.data.message ? response.data.message : repoUpdate.i18n.error);
					return;
				}

				var current = $branch.val();
				$branch.empty();

				(response.data.branches || []).forEach(function (branch) {
					$branch.append(
						$('<option>', {
							value: branch,
							text: branch,
							selected: branch === current
						})
					);
				});

				showMessage($message, 'success', repoUpdate.i18n.branchesLoaded);
			})
			.fail(function () {
				showMessage($message, 'error', repoUpdate.i18n.error);
			})
			.always(function () {
				$button.prop('disabled', false).text(repoUpdate.i18n.fetchBranches);
			});
	});

	$('#repo-update-test-connection').on('click', function () {
		var $button = $(this);
		var $message = $('#repo-update-form-message');

		$button.prop('disabled', true).text(repoUpdate.i18n.loading);

		$.post(repoUpdate.ajaxUrl, {
			action: 'repo_update_test_connection',
			nonce: repoUpdate.nonce,
			owner: $('#owner').val(),
			name: $('#name').val(),
			token: $('#token').val(),
			id: $('input[name="id"]').val()
		})
			.done(function (response) {
				if (!response.success) {
					showMessage($message, 'error', response.data && response.data.message ? response.data.message : repoUpdate.i18n.error);
					return;
				}

				showMessage($message, 'success', response.data.message || repoUpdate.i18n.connectionOk);
			})
			.fail(function () {
				showMessage($message, 'error', repoUpdate.i18n.error);
			})
			.always(function () {
				$button.prop('disabled', false).text(repoUpdate.i18n.testConnection);
			});
	});
})(jQuery);
