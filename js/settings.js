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

	$('#button-add-post-type').click(function(event) {
		var newForm = $('#div-add-post-type').clone();
		$('form', newForm).on('submit', function(event) {
			event.preventDefault();
			var self = this;
			var data = $(self).serialize();
			$.post(ajaxurl, data, function(response) {
				response = $.parseJSON(response);
				if (response['status'] === "success") {
					var post_type = $(self['post_type']).val();
					var post_type_label = $('option:selected', self['post_type']).text().trim();
					var api_url = $(self['api_url']).val();
					var custom_title_field = $(self['custom_title_field']).val();
					debugger;
					syncable_post_types['post_types'][post_type].push({
						api_url: api_url,
						post_type: post_type,
						post_type_label: post_type_label,
						custom_title_field: custom_title_field
					});
					render_syncable_post_types_list();
					// append to list
					//$('.syncable-post-types').append("<li>" + option_label + "<br><span>" + api_url + "</span></li>");
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

	$(document).on('click','.delete-post-type', function(event){
		event.preventDefault();
		var self = this;
		var post_type = $(self).attr('data-post-type');
		var id = $(self).attr('data-id');
		var data = {
			action: "grape_delete_post_type",
			post_type: post_type,
			id: id
		};
		$.post(ajaxurl, data, function(response) {
			response = $.parseJSON(response);
			if (response['status'] === "success") {
				// remove entry
				// $(self).parent('li').remove()
				syncable_post_types['post_types'][post_type].splice(id,1);
				render_syncable_post_types_list();
			} else {
				alert(response['error']);
			}
		});
	});

	function render_syncable_post_types_list() {
		var source = $('#template1').html();
		var template = Handlebars.compile(source);
		var result = template(syncable_post_types);
		$('#placeholder-syncable-post-types').html(result);
	}
	render_syncable_post_types_list();
});
