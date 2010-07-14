<?php

/*
 =====================================================
 Shrimp - by Dan Benjamin, Hivelogic Corporation
 -----------------------------------------------------
 http://hivelogic.com/projects/shrimp
 -----------------------------------------------------
 Copyright (c) 2009 Hivelogic Corporation
 =====================================================
 This software is released under the
 GNU General Public License (v3.0)
 http://www.gnu.org/licenses/gpl-3.0.txt
 =====================================================
 File: pi.shrimp.php
 -----------------------------------------------------
 Purpose: Shorten website URLs
 =====================================================
*/

/*
 * Forked by @danott and later by @erikreagan
 * 
 * Dan wanted to have one template as the redirector for multiple weblogs.
 * His solution was to use the EE preference for weblog search results
 * path to build the longurl for the entry.
 *
 * Also changed some defaults along the way, and added some new ones
 * for increased functionality for his needs.
 * 
 * Erik ported the plugin to include compatibility for both EE 1.x and EE 2.x
 */


$plugin_info = array(
	'pi_name'        =>	'Shrimp',
	'pi_version'     =>	'1.5',
	'pi_author'      =>	'Dan Benjamin',
	'pi_author_url'  =>	'http://hivelogic.com/projects/shrimp/',
	'pi_description' =>	'Shortens website URLs.',
	'pi_usage'       =>	Shrimp::usage()

);


/**
 * Super Class
 *
 * @package		Shrimp
 * @category	Plugin
 * @author		Dan Benjamin
 * @link			http://hivelogic.com/projects/shrimp
 */

class Shrimp {

	var $default_redirect_template = "u"; 
	
	// @danott fork - a new default
	// In some scenarious, I'd rather just go to the homepage rather than show an error.
	var $do_forward_instead_of_error = TRUE;
	
	/* 
	 * from @erikreagan
	 * Initialize objects for EE1 and EE2 use in a single plugin
	 * They're later referenced in the constructor based on app version number
	 */
	var $DB;
	var $FNS;
	var $OUT;
	var $TMPL;
	

	// initialize the variables
	var $entry_id	=	'';
	var $template	=	'';
	var $title 		=	'';
	var $path 		=	'';
	var $url 		=	'';
	
	/* 
	 * from @erikreagan
	 * This variable will be for our DB table nomenclature.
	 * It's defined in our constructor
	 */
	var $nomenclature = '';

	// retrieve the data and filter or reformat it
	function Shrimp()
	{
		
		/* 
		 * from @erikreagan
		 * EE version check to properly reference our EE objects
		 */
		if (version_compare(APP_VER, '2', '<'))
		{
			// EE 1.x is in play
			global $DB, $FNS, $OUT, $TMPL;
			$this->DB   =& $DB;
			$this->FNS  =& $FNS;
			$this->OUT  =& $OUT;
			$this->TMPL =& $TMPL;
			$this->nomenclature = 'weblog';
		} else {
			// EE 2.x is in play
			$this->EE	=& get_instance();
			$this->DB   =& $this->EE->db;
			$this->FNS  =& $this->EE->functions;
			$this->OUT  =& $this->EE->output;
			$this->TMPL =& $this->EE->TMPL;
			$this->nomenclature = 'channel';
		}

		// pre-sanitize the entry_id as it may be used in a SQL query
		$this->entry_id = (int)$this->DB->escape_str($this->TMPL->fetch_param('entry_id'));

		/* @danott fork
		 * This was on multiple lines. Moved it down to one for simplicity sake.
		 * Either grabs the template from the parameters cleans it up, or uses the default.
		 *
		 * fetch_param returns escaped characters, so we need to convert the slashes
		 * if no template group is specified, use the default.
		 */
		$this->template = ( ! $this->TMPL->fetch_param('template')) ? $this->default_redirect_template : str_replace(SLASH,'/',$this->TMPL->fetch_param('template'));

		// sanitize the title so it will be usable in an <a href> tag
		$this->title = htmlentities($this->TMPL->fetch_param('title'));

		// create the shortened URL path without the protocol and domain
		$this->path = ('/'.$this->template.'/'.$this->entry_id);

		// create the shortened URL
		$this->url = $this->FNS->create_url($this->path,false);

	}

	// generate a valid HTML link
	// uses the shortened URL in place of a title if no title is specified
	function link()
	{
		$title = ($this->title === '') ? $this->url : $this->title;
		return '<a href="'.$this->url.'" title="'.$title.'">'.$title.'</a>';
	}

	// generate a meta tag suitable for placement within the <head> block of a webpage
	// will retrn NULL if no entry_id is assigned, allowing placement in a sitewide header
	function meta_tag()
	{
		return ($this->entry_id === '') ? NULL : '<link rel="shorturl" href="'.$this->url.'" />';
	}

	// return the shortened URL
	function permalink()
	{
		return $this->url;
	}

	// provides redirection from the shortened URL to the full-length URL using the
	// specified entry_id and template group (for use in the redirection template)
	function redirect() {

		/* @danott
		 * To me, it seemed like a more elegant solution to not have to pass a template parameter
		 * but rarther to use some preferences we already have stored. Using the entry's, weblog's
		 * search result path, we can build the same url that a search page would take this entry to.
		 * It may not work for all people, having not set that preference, but it works for me.
		 * This way you can have one template 's' to provide the shortcut url for multiple weblogs.
		 */
		
		/*
		 * from @erikreagan
		 * Query has been written to use "weblog" or "channel" based on EE version
		 */
		
		$query = $this->DB->query("SELECT exp_".$this->nomenclature."_titles.url_title, exp_".$this->nomenclature."s.search_results_url
						FROM exp_".$this->nomenclature."_titles
						JOIN exp_".$this->nomenclature."s ON exp_".$this->nomenclature."_titles.".$this->nomenclature."_id = exp_".$this->nomenclature."s.".$this->nomenclature."_id
						WHERE exp_".$this->nomenclature."_titles.entry_id = ".$this->entry_id);		
		
		// display an error if the specified entry_id doesn't exist
		if ($query->num_rows == 0)
		{
			
			// @danott fork - do Dan Benjamin's original error message
			if ( ! $this->do_forward_instead_of_error)
			{
				$this->OUT->show_user_error('general', 'Invalid or nonexistent entry.');
				exit();
			}
			
			// @danott fork - otherwise, just create a link to the homepage.
			$long_url = $this->FNS->create_url('/',false);			
		} else {
			
			/*
			 * from @erikreagan
			 * If we're in EE 2.x we need to reference the result_object
			 * We do this so we can use the same object with our result regardless of the EE version in play
			 */
			$query->row = (version_compare(APP_VER, '2', '<')) ? $query->row : get_object_vars($query->result_object[0]) ;
			
			// create the full-length URL using the specified template group and the retrieved url_title
			// If the weblog has a search_results_url we use that, otherwise we use the template paramter passed to the plugin
			// Using the longhand syntax for readability
			if ($query->row['search_results_url'] != '') {
				$long_url = $query->row['search_results_url'].$query->row['url_title'];
			} else {
				$long_url = $this->FNS->create_url($this->template.'/'.$query->row['url_title'],false);
			}
		}

		// issue a redirect to the full-length URL with a 301 status and exit
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: ".$long_url);
		exit();

	}

	// return the path without the protocol and domain
	function relative_url()
	{
		return $this->path;
	}

	// plugin usage
	function usage()
	{
		ob_start();
?>

Shrimp is an ExpressionEngine plugin that provides URL shortening functionality. Shrimp is different from similar plugins in that it provides features like customizable link generation, access to the shortened URL for your own custom links, smart meta tags which hide themselves on non-entry pages, access to the relative URL (without the protocol or domain), and more, without any unnecessary database access.

Shrimp transforms long URLs like this:

	http://site.com/weblog/entries/some-article-title

into shortened URLs like this:

	http://site.com/u/123

Shrimp then provides redirection from the shortened URL to the long URL through the use of a simple, one-line template.

Shrimp is different from similar plugins in that it provides features like customizable link generation, access to the shortened URL for your own custom links, smart meta tags which hide themselves on non-entry pages, access to the relative URL (without the protocol or domain), and more, without any unnecessary database access.

It is recommended that you remove the "index.php" from your URLs as described here (although Shrimp will work either way):

http://expressionengine.com/wiki/Remove_index.php_From_URLs

== USAGE ==

Integrating Shrimp into your ExpressionEngine website is a straight forward process.

Create a new template group for the redirection. Give the group a short name, such as "u", in order to keep the URL as small as possible (Shrimp will actually assume the default template group name "u" if you don't specify one).

Paste the following line into the index template in the new template group. You must replace "weblog/entries" in the example below with the name of your individual entry view template:

{exp:shrimp:redirect template="weblog/entries" entry_id="{segment_2}"}

Add the following line to the <head>...</head> block of your entry view template, replacing the template value with the name of the template group you created in the first step (or omit it and Shrimp will use "u"):

{exp:shrimp:meta_tag template="u" entry_id="{entry_id}"}

Display the short link on your site, within an {exp:weblog:entries} tag block. Shrimp can generate a full <a href> tag with a customizable title like this:

{exp:shrimp:link title="Short URL" template="u" entry_id="{entry_id}"}

This will generate an <a href> tag like this:

<a href="http://site.com/u/123" title="Short URL">Short URL</a>

You can specify anything you want for the link title, such as the entry's title:

{exp:shrimp:link title="{title}" template="u" entry_id="{entry_id}"}

This will generate an <a href> tag like this:

<a href="http://site.com/u/123" title="My Entry Title">My Entry Title</a>

If you'd prefer to create your own custom links, you can access the shortened URL directly for use in your own links or for display using the "permalink" method like this:

{exp:shrimp:permalink template="u" entry_id="{entry_id}"}

This will output just the shortened permalink without the <a href> tag, like this:

http://site.com/u/123

If you just want to access the shortened path without the protocol (http://) and site name, you can call the "relative_url" method, like this:

{exp:shrimp:relative_url template="u" entry_id="{entry_id}"}

This will return just the path of the short URL, like this:

/u/123

== Change Log ==

1.0

* Initial release.

1.0.1

* Fixed a superfluous slash in the <a href> tag.
* Adding a check for existing entry_id. Will now display an error if not found.

1.0.2.

* Casting entry_id as an integer to prevent PHP errors

1.5

* Updated by Erik Reagan (@erikreagan) to support both ExpressionEngine 1.x and 2.x

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
	// end of usage function

}
// end of Shrimp class

/* end of file pi.shrimp.php */
/* location: ./system/plugins/pi.shrimp.php */