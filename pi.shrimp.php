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


$plugin_info = array(
						'pi_name'					=>	'Shrimp',
						'pi_version'			=>	'1.0',
						'pi_author'				=>	'Dan Benjamin',
						'pi_author_url'		=>	'http://hivelogic.com/projects/shrimp/',
						'pi_description'	=>	'Shortens website URLs.',
						'pi_usage'				=>	Shrimp::usage()
					);


/**
 * Super Class
 *
 * @package 	Shrimp
 * @category	Plugin
 * @author		Dan Benjamin
 * @link 			http://hivelogic.com/projects/shrimp
 */

class Shrimp {

	// set a default template group name in case one is not specified
	var $default_template = "u";

	// initialize the variables
	var $entry_id	=	'';
	var $template	=	'';
	var $title 		=	'';
	var $path 		=	'';
	var $url 			=	'';

	// retrieve the data and filter or reformat it
	function Shrimp()
	{

		global $DB, $TMPL, $FNS;

		// pre-sanitize the entry_id as it may be used in a SQL query
		$this->entry_id = $DB->escape_str($TMPL->fetch_param('entry_id'));

		// fetch_param returns escaped characters, so we need to convert the slashes
		// if no template group is specified, default to "u"
		$template = $TMPL->fetch_param('template');
		if ($template === '')
		{
			$this->template = $this->default_template;
		}
		else
		{
			$this->template = str_replace(SLASH,'/',$template);
		}

		// sanitize the title so it will be usable in an <a href> tag
		$this->title = filter_var($TMPL->fetch_param('title'), FILTER_SANITIZE_SPECIAL_CHARS);

		// create the shortened URL path without the protocol and domain
		$this->path = ('/'.$this->template.'/'.$this->entry_id);

		// create the shortened URL
		$this->url = $FNS->create_url($this->path,false);

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

		global $DB, $FNS;

		// get the url_title for the entry based on the specified (and pre-sanitized) entry_id
		$query = $DB->query("SELECT url_title
													FROM exp_weblog_titles
													WHERE entry_id = ".$this->entry_id);

		// create the full-length URL using the specified template group and the retrieved url_title
		$long_url = $FNS->create_url($this->template.'/'.$query->row['url_title'],false);

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

Initial release.

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
