(function () {
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

	jQuery(function ($) {

		var select2 = {
			init: function () {
				try {
					$(document.body)
							.on('nm-favourites-select2-init', this.activate_select2)
							.trigger('nm-favourites-select2-init');
				} catch (e) {
					window.console.log(e);
				}
			},

			activate_select2: function () {
				$('.nm-favourites-select2').select2();
				select2.post_search();
			},

			post_search: function () {
				$(':input.nm-favourites-object-search').each(function () {
					var select2_args = {
						ajax: {
							url: $(this).data('ajax_url'),
							dataType: 'json',
							delay: 250,
							cache: true,
							method: 'POST',
							data: function (params) {
								return {
									term: params.term,
									action: 'nm_favourites_post_action',
									nm_favourites_post_action: $(this).data('action'),
									post_types: $(this).attr('data-post_types'),
									_wpnonce: $(this).data('nonce')
								};
							},
							processResults: function (data) {
								var terms = [];
								if (data) {
									$.each(data, function (id, text) {
										terms.push({
											id: id,
											text: text
										});
									});
								}
								return {
									results: terms
								};
							}
						}
					};

					$(this).select2(select2_args);

				});
			}
		};
		select2.init();


		// Show confirm dialog before bulk deleting categories
		$(document.body).on('click', '#doaction, #doaction2', function () {
			var self = $(this);
			var checked_checkboxes = $('.check-column input[type="checkbox"]:checked');
			var pos = self.is('#doaction') ? 'top' : 'bottom';
			var action = $('select#bulk-action-selector-' + pos).val();
			var arr = ['delete', 'delete_tags'];

			if (0 < checked_checkboxes.length && -1 !== arr.indexOf(action)) {
				return showNotice.warn();
			}
		});

		// If a parent category is selected, we are dealing with a child category, so hide button settings fields
		$(document.body).on('change', 'select#parent', function () {
			var $container = $('.category_button_fields');
			if ('' === this.value) {
				$container.show().find('fieldset').attr('disabled', false);
			} else {
				$container.hide().find('fieldset').attr('disabled', true);
			}
		});

		/**
		 * When post type is selected for button, update associated inputs
		 * The input to be updated are the exclude and include fields for posts and terms.
		 * These are populated based on the post types selected.
		 */
		$(document.body).on('change', 'select#post_types', function () {
			$('.nm-favourites-object-search').attr('data-post_types', JSON.stringify($(this).val()));
		});
		$('select#post_types').trigger('change');

		// Toggle inputs for custom button when the custom button checkbox is toggled
		$(document.body).on('change', '.nm-favourites-custom-btn', function () {
			var section = this.closest('section');
			var $elementToToggle = $('.nm-favourites-custom-btn-toggle', section);
			if (true === this.checked) {
				$elementToToggle.show();
			} else {
				$elementToToggle.hide();
			}
		});
		$('.nm-favourites-custom-btn').trigger('change');

		// Hide the include/exclude objects and categories fieldset for specfic object types
		function maybe_hide_include_exclude_fieldset() {
			var value = $('select#object_type').val();
			if (-1 !== ['comment'].indexOf(value)) {
				$('fieldset#include_exclude').attr('disabled', true).closest('.postbox').hide();
			} else {
				$('fieldset#include_exclude').attr('disabled', false).closest('.postbox').show();
			}
		}

		maybe_hide_include_exclude_fieldset();

		// Perform actions when object type is selected
		$(document.body).on('change', 'select#object_type', function () {
			maybe_hide_include_exclude_fieldset();

			// If the object type selected is for a specific post type (such as product), select the post type
			var $post_type_select = $('select#post_types');
			if (-1 !== ['product'].indexOf(this.value)) {
				for (var item of $post_type_select[0].options) {
					if (this.value === item.value) {
						$post_type_select.val(this.value).select2().trigger('change');
					}
				}
			}

			// Send the selected object type value via ajax to server
			var args = {
				btn: this,
				block: [this, 'select#display\\[display_1\\]\\[position\\]'],
				postdata: {
					object_type: this.value,
					nm_favourites_post_action: 'object_type_selected'
				}
			};
			postAction(args);
		});

	});

})();
