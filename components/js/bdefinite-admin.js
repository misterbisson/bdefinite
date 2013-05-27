(function($){

		$("#bdefinite-details").insertAfter("#titlediv");
		$("#title-prompt-text").contents().replaceWith("Name term here");
		
		// for the description section
		$("#post-body-content").append('<div id="bdefinite-description" class="postbox"><div class="handlediv" title="Click to toggle"><br></div><h3 class="hndle"><span>Definition</span></h3><div class="inside"></div>');
		$('#bdefinite-description .inside').append($("#postdivrich"));
})(jQuery);

