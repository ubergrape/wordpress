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

			console.log(getPercent, getProgressWrapWidth, progressTotal);

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

});
