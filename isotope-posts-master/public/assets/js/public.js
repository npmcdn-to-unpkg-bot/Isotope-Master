(function ($) {
	"use strict";

	$(function () {

		// Grab initial filter if there's a hash on the URL
		var initialFilter = window.location.hash && ( '.' + window.location.hash.substr(1) ) || '*';

		var qsRegex;
		var buttonFilter;
		var selector;

		
		//Initilize Isotope
		var $container = $('#iso-loop').imagesLoaded( function () {
			$container.fadeIn().isotope({
				itemSelector : '.iso-post',
				// layoutMode : iso_vars.iso_layout,
				layoutMode : 'packery',
				// filter : initialFilter
			
				filter: function() {
			    var $this = $(this);
		    	var searchResult = qsRegex ? $this.text().match( qsRegex ) : true;
		    	var buttonResult = selector ? $this.is( selector ) : true;
		    	return searchResult && buttonResult;
		  		}			
			});
		});

		$(window).on('resize', function(){
			    $('.grid').packery('layout');
			});

		//Initialize packery
		var $grid = $('.grid').packery({
					itemSelector:'.grid-item',
				});
			var text_color;

			$grid.on( 'click', '.iso-title, .answer', function() {
			  // change size of item by toggling large class
			  // $(  event.currentTarget  ).toggleClass('gigante');
			  jQuery( this ).parent().toggleClass('gigante');
			  // trigger layout after item size changes
			  $grid.packery('layout');
			  var clicked_item = jQuery(this).parent();
			  if( clicked_item.hasClass('gigante') ){
			  	// clicked_item.find('.iso-title').css('color', 'black');
			  	text_color = clicked_item.find('.iso-title').css("color");
			  	clicked_item.find('.iso-title').css('color', '#222');
			  	clicked_item.find('.answer').show();
			  	clicked_item.find('.leave_comments').show();	
			  	clicked_item.find('.rating').show();
			  	clicked_item.find('.social_share').show();

			  }else{
			  	clicked_item.find('.answer').hide();
			  	clicked_item.find('.iso-title').css('color', text_color);
			  	clicked_item.find('.leave_comments').hide();
			  	clicked_item.find('.rating').hide();
			  	clicked_item.find('.social_share').hide();		
			  }
			});	



		// Initialize infinite scroll if required
		if ( iso_vars.iso_paginate == 'yes' ){
			$container.infinitescroll({
				loading: {
					finishedMsg: iso_vars.finished_message,
					img: iso_vars.loader_gif,
					msgText: "",
					selector: ".iso-posts-loading",
					speed: 0,
				},
				binder: $(window),
				navSelector: ".iso-pagination",
				nextSelector: ".more-iso-posts a",
				itemSelector: ".iso-post",
				path: function generatePageUrl(currentPageNumber) {
					if ( $('body').hasClass('home') ) {
						return (iso_vars.page_url + 'page/' + currentPageNumber + "/");
					} else {
						return (iso_vars.page_url + currentPageNumber + "/");
					}
				},
				prefill : true
			},
				function ( newElements ) {
					var $newElems = $( newElements ).hide();
					$newElems.imagesLoaded(function () {
						$newElems.fadeIn();
						$container.isotope( 'appended', $newElems );
					});
				}
			);
		}

		// Create helper function to check if posts should be added after filtering
		function needPostsCheck() {
			if ( iso_vars.iso_paginate == 'yes' ) {
				if ( ( $container.height() < $(window).height() ) || ( $container.children(':visible').length == 0 ) ){
					$container.infinitescroll('retrieve');
				}
			} else {
				return false;
			}
		}

		// Check if posts are needed for filtered pages when they load
		$container.imagesLoaded(function () {
			if ( window.location.hash ) {
				needPostsCheck();
			}
		});

		// Set up the click event for filtering
		$('#filters').on('click', 'button', function ( event ) {
			event.preventDefault();
			selector = $(this).attr('data-filter');
			var niceSelector = selector.substr(1);
			
			qsRegex = new RegExp( $('.quicksearch').val(), 'gi' );
			history.pushState ? history.pushState( null, null, '#' + niceSelector ) : location.hash = niceSelector;
			
			$container.isotope();
			// var $height = $('.grid').height();
			// $('.iso-container').height($height);

			needPostsCheck();
		});
		
		$(document).ready(function(){
			if( $('.word-search').val()!==''&& $('.word-search')!==null){
				qsRegex = new RegExp( $('.word-search').val(), 'gi' );
				// $container.isotope();
				// var $height = $('.grid').height();
				// $('.iso-container').height($height);	
			}

			// if($('body').hasClass('page-id-137')){
			// 	$('body').find('.entry-title').remove();
			// }
				
		});

		var $quicksearch = $('.quicksearch').keyup( debounce( 
			function() {
			  	qsRegex = new RegExp( $quicksearch.val(), 'gi' );
			  	$container.isotope();
			 
		}, 200 ) );

		$('.button-group').each( function( i, buttonGroup ) {
		  var $buttonGroup = $( buttonGroup );
		  $buttonGroup.on( 'click', 'button', function() {
		    $buttonGroup.find('.is-checked').removeClass('is-checked');
		    $( this ).addClass('is-checked');
		  });
		});
  
		function debounce( fn, threshold ) {
		  var timeout;
		  return function debounced() {
		    if ( timeout ) {
		      clearTimeout( timeout );
		    }
		    function delayed() {
		      fn();
		      timeout = null;
		    }
		    setTimeout( delayed, threshold || 100 );
		  };
		}

	});

}(jQuery));
