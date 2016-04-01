jQuery(function($) {

	moveProgressBar();

	$(window).resize(function() {
		moveProgressBar();
	});


	function moveProgressBar() {
		$('.grape-progress-wrap').each(function(i, e) {

			var getPercent = ($(e).attr('data-progress-percent') / 100);
			var getProgressWrapWidth = $(e).width();
			var progressTotal = getPercent * getProgressWrapWidth;
			var animationLength = $(e).data('speed');

			$(e).find('.grape-progress-bar').stop().animate({
				left: progressTotal
			}, animationLength);
		});
	}

	function setProgressPercentage(p) {
		$('.grape-progress-wrap').attr('data-progress-percent', p);
	}

	function done() {
		$('.grape-progress-container').addClass('hidden');
		$('.grape-progress-done').removeClass('hidden');
	}


	$('#grape-full-sync').click(function(event) {
		event.preventDefault();

		$('.grape-progress-container').removeClass('hidden');

		// reset progress bars
		setProgressPercentage(0);
		$('.grape-progress-bar').css("left", 0);
		$('.grape-progress-done').addClass('hidden');

		var data = {
			'action': 'grape_full_sync'
		};

		var postsTotal = 0;
		var nextPostId = null;
		var i = 0;

		data = {
			'action': 'grape_full_sync_start',
		};
		$.post(ajaxurl, data, function(response) {
			var iterate = function() {
				data = {
					'action': 'grape_full_sync',
				};
				if (null !== nextPostId) {
					data['postId'] = nextPostId;
					console.log("sync post nr", i, "id", nextPostId);
				}
				$.post(ajaxurl, data, function(response) {
					response = JSON.parse(response);
					postsTotal = response['postsTotal'];
					nextPostId = response['nextPostId'];
					i += 1;
					setProgressPercentage(Math.round((i/postsTotal)*100));
					moveProgressBar();
					if (i<postsTotal) {
						iterate();
					} else {
						done();
					}
				});
			};

			iterate();
		});

	});

	$('#button-add').click(function(event) {
		var newForm = $('#div-add-post-type').clone();
		$('form.taxonomy', newForm).css('display', 'none');

		$('select[name="syncable_type"]', newForm).on('change', function(event){
			var value = $('option:selected', this).val();
			var not_value = $('option:not(:selected)', this).val();
			console.log(not_value, value);
			$('form.' + not_value, newForm).css('display', 'none')
			$('form.' + value, newForm).css('display', '')
		});
		$('form', newForm).on('submit', function(event) {
			event.preventDefault();
			var self = this;
			var data = $(self).serialize();
			$.post(ajaxurl, data, function(response) {
				response = $.parseJSON(response);
				if (response['status'] === "success") {
					var syncable_type = $(self['syncable_type']).val();
					var type = $(self['type']).val();
					var type_label = $('option:selected', self['type']).text().trim();
					var api_url = $(self['api_url']).val();
					var custom_title_field = $(self['custom_title_field']).val();
					var custom_url = $(self['custom_url']).val();

					// apend to list
					if (!syncables[syncable_type].hasOwnProperty(type)) {
						syncables[syncable_type][type] = [];
					}
					syncables[syncable_type][type].push({
						api_url: api_url,
						type: type,
						type_label: type_label,
						custom_title_field: custom_title_field,
						custom_url: custom_url
					});

					// render
					render_syncables_list();

					// remove form
					$(self).closest('.div-add-post-type').remove()
				} else {
					alert(response['error']);
				}
			});
		});
		newForm.css('display', '');
		$('#placeholder-add-post-type').append(newForm);
	});

	$(document).on('click','.delete-syncable', function(event){
		event.preventDefault();
		var self = this;
		var syncable_type = $(self).attr('data-syncable-type');
		var type = $(self).attr('data-type');
		var id = $(self).attr('data-id');
		var data = {
			action: "grape_delete_syncable",
			syncable_type: syncable_type,
			type: type,
			id: id
		};
		$.post(ajaxurl, data, function(response) {
			response = $.parseJSON(response);
			if (response['status'] === "success") {
				// remove entry
				// $(self).parent('li').remove()
				syncables[syncable_type][type].splice(id,1);
				render_syncables_list();
			} else {
				alert(response['error']);
			}
		});
	});

	function render_syncables_list() {
		var source = $('#template1').html();
		var template = Handlebars.compile(source);
		var result = template(syncables);
		$('#placeholder-syncables').html(result);
	}
	render_syncables_list();
});
