var nm_favourites = (function (exports) {
	'use strict';

	function block(selector) {
		var toBlock = Array.isArray(selector) ? selector : [selector];
		toBlock.forEach(function (item) {
			if (!(jQuery(selector).is('nm-favourites-loading') || jQuery(selector).parents('.nm-favourites-loading').length)) {
				jQuery(item).addClass('nm-favourites-loading');
			}
		});
	}

	function unblock(selector) {
		var toBlock = Array.isArray(selector) ? selector : [selector];
		toBlock.forEach(function (item) {
			jQuery(item).removeClass('nm-favourites-loading');
		});
	}

	function closeDialog() {
		// Get the id of the currently opened dialog
		var id = jQuery('.ui-dialog.modal').find('.ui-dialog-content').prop('id');
		jQuery('#' + id).dialog('close');
	}

	function dialog(dialog) {
		var $dialog = jQuery(dialog);
		var newOptions = JSON.parse(dialog.dataset.options);

		var options = {
			show: 200, // animate showing of dialog
			hide: 200, // animate hiding of dialog
			minHeight: 0, //Prevent padding for minimal content
			modal: true,
			open: function () {
				var $closeBtn = jQuery(this).closest(".ui-dialog").find(".ui-dialog-titlebar-close");

				// Add .ui-button class to close button to prevent bootstrap conflict with button appearance
				$closeBtn.addClass('ui-button');

				jQuery('.ui-widget-overlay').on('click', function () {
					$dialog.dialog('close');
				});

				if ('toast' === newOptions.type) {
					/**
					 *  Remove focus on the close button to prevent the outline from showing on toasts in order not to
					 *  obscure the text message
					 */
					$closeBtn.attr('tabindex', -1);
					setTimeout(function () {
						$dialog.dialog('instance') ? $dialog.dialog('close') : '';
					}, 5000);
				}

				// Allow select2 input to receive focus in modal
				if (jQuery.ui && jQuery.ui.dialog && jQuery.ui.dialog.prototype._allowInteraction) {
					var ui_dialog_interaction = jQuery.ui.dialog.prototype._allowInteraction;
					jQuery.ui.dialog.prototype._allowInteraction = function (event) {
						if (jQuery(event.target).is(".select2-search__field")) {
							return true;
						}
						return ui_dialog_interaction.apply(this, arguments);
					};
				}
			},
			close: function () {
				$dialog.dialog('destroy');
			}
		};

		options = Object.assign(options, newOptions);
		$dialog.dialog(options);
	}

	/**
	 * Show a dialog template
	 * @param {string} template Template to show in a dialog
	 * @returns {undefined}
	 */
	function showTemplate(template) {
		dialog(jQuery(template)[0]);
	}

	function showToast(notice) {
		var notices = notice instanceof Array ? notice : [notice];
		var toastClass = 'nm-favourites-toaster';

		if (!document.querySelector('.' + toastClass)) {
			jQuery(document.body).append('<div class="' + toastClass + '"></div>');
		}

		notices.forEach(function (el) {
			dialog(jQuery(el)[0]);
		});
	}

	// Replace templates in the dom when they have been retrieved from the server
	function replaceTemplates(templates) {
		for (var key in templates) {
			var collection = document.querySelectorAll(key);
			if (collection) {
				var template = templates[key];
				collection.forEach(function (coll) {
					var wrap = document.createElement('template');
					wrap.innerHTML = template;
					var newTemplate = wrap.content.children[0];
					newTemplate = ('undefined' === typeof newTemplate) ? '' : newTemplate;
					coll.replaceWith(newTemplate);
					jQuery(document.body).trigger('nm_favourites_replaced_template', {
						key: key,
						template: newTemplate
					});
				});
			}
		}
	}

	/**
	 * Go to a url
	 * @param {string} url
	 */
	function redirect(url) {
		if (-1 === url.indexOf('https://') || -1 === url.indexOf('http://')) {
			window.location = url;
		} else {
			window.location = decodeURI(url);
		}
	}

	/**
	 * Perform standard operations with the response from an ajax request
	 */
	function processResponse(response, args) {
		if (response.log) {
			console.log(response);
		}

		if (args && args.block) {
			unblock(args.block);
		}

		if ((args && args.close_dialog) || response.close_dialog) {
			closeDialog();
		}

		if (response.show_template) {
			showTemplate(response.show_template);
		}

		if (response.toast_notice) {
			showToast(response.toast_notice);
		}

		if (response.replace_templates) {
			replaceTemplates(response.replace_templates);
		}

		if (response.redirect) {
			redirect(response.redirect);
		}
	}

	function postAction(args) {
		if (args.btn && args.btn.dataset) {
			if ((args.btn.dataset.alert && !window.alert(args.btn.dataset.alert)) ||
					(args.btn.dataset.confirm && !window.confirm(args.btn.dataset.confirm))) {
				return false;
			}
		}

		if (args.block) {
			block(args.block);
		}

		if (!args.postdata) {
			return;
		}

		var postParams = {
			action: 'nm_favourites_post_action',
			_wpnonce: nm_favourites_vars.nonce,
			ajax_url: nm_favourites_vars.ajax_url
		};

		var postdata = Object.assign({}, postParams, args.postdata);

		var postOptions = {
			url: postdata.ajax_url,
			data: postdata,
			success: function (response) {
				response = response || {};
				delete(postdata['action']);
				delete(postdata['_wpnonce']);
				response.postdata = postdata;

				processResponse(response, args);

				jQuery(document.body).trigger('nm_favourites_post_action_response', response);

				return response;
			}
		};

		return jQuery.post(postOptions);
	}

	var $ = jQuery;

	// Toggle category info and update containers
	$(document.body).on('click', '.nm-favourites-category .edit_cat', function (e) {
		e.preventDefault();
		$('#nm_favourites_cat_info').toggle();
		$('#nm_favourites_cat_update').toggle();
		$('#nm_favourites_cat_update :input[type!=hidden]:first').focus();
	});

	// Save category edit form
	$(document.body).on('click', '.nm-favourites-category .save_cat', function () {
		var form = $('#nm_favourites_cat_update');
		var args = {
			block: form,
			postdata: {
				formdata: form.serialize(),
				nm_favourites_post_action: 'save_category'
			}
		};

		var promise = nm_favourites.postAction(args);
		promise.done(function (response) {
			if (response.replace_templates) {
				$('#nm_favourites_cat_update').hide();
			}
		});
	});

	// Remove tag/category
	$(document.body).on('click', '.nm_favourites_shortcode_1', function (e) {
		e.preventDefault();
		if (this.dataset.confirm && !confirm(this.dataset.confirm)) {
			return false;
		}

		var block = this;
		if (this.dataset.location && 'table' === this.dataset.location) {
			block = this.closest('tr'); // row
		}

		var args = {
			block: block,
			postdata: Object.assign({}, this.dataset)
		};

		nm_favourites.postAction(args);
	});

	// Table navigation
	$(document.body).on('click', '.nm_favourites_nav', function (e) {
		e.preventDefault();

		var tableId = '#' + this.dataset.target_id;

		var args = {
			block: tableId,
			postdata: {
				nm_favourites_post_action: 'paginate',
				table_dataset: $(tableId)[0].dataset,
				direction: this.dataset.action
			}
		};
		nm_favourites.postAction(args);
	});

	jQuery(document.body).on('click', '[data-autopost="1"][data-nm_favourites_post_action]', pre_post_action);

	function pre_post_action() {
		var postdata = Object.assign({}, this.dataset);
		var args = {
			btn: this,
			block: [this],
			postdata: postdata
		};
		postAction(args);
	}

	exports.postAction = postAction;

	Object.defineProperty(exports, '__esModule', { value: true });

	return exports;

})({});
