<?php

 /**
 * @author  Walak Paul Auteur <workcraft.eu>
 * @version 1.0
 * @author  Mohammed Rhamnia <medrhamnia.wordpress.com>
 * @version 1.1 
 */
class sfWidgetFormTextareaXinha extends sfWidgetFormTextarea
{

	private $_additionalConfig = '';

  protected function configure($options = array(), $attributes = array())
  {
    $this->addOption('lang', 'fr');
    $this->addOption('config', '');
    $this->addOption('plugins', array('Stylist', 'ExtendedFileManager', 'InsertPagebreak' ));
  }

  /**
   * @param  string $name        The element name
   * @param  string $value       The value selected in this widget
   * @param  array  $attributes  An array of HTML attributes to be merged with the default HTML attributes
   * @param  array  $errors      An array of errors for the field
   *
   * @return string An HTML tag string
   *
   * @see sfWidgetForm
   */
  public function render($name, $value = null, $attributes = array(), $errors = array())
  {
    $textarea = parent::render($name, $value, $attributes, $errors);

		$generatedId = $this->generateId($name);
		$this->configurePlugins( $this->getOption('plugins') );

    $js = sprintf(<<< EOF

<script type="text/javascript">


function xinha_init_%s()
{
	var xinha_plugins = [ "%s" ];
	var xinha_editors = [ "%s" ];
  _editor_skin = "xp-blue";
  

  if(!Xinha.loadPlugins(xinha_plugins, xinha_init_%s)) return;

  var xinha_config = new Xinha.Config();
  %s
  %s
	xinha_config.formatblock={"- format -":"","Title":"h1","Subtitle":"h2","Chapter":"h3","Section":"h4","Heading 5":"h5","Heading 6":"h6",Normal:"p",Address:"address",Formatted:"pre",Code:"blockquote"};
  xinha_editors = Xinha.makeEditors(xinha_editors, xinha_config, xinha_plugins);
  Xinha.startEditors(xinha_editors);
}
Xinha.addOnloadHandler(xinha_init_%s);

</script>

EOF
    ,
      $generatedId,
      implode('", "', $this->getOption('plugins')),
      $generatedId,
      $generatedId,
      $this->_additionalConfig,
      $this->getOption('config'),
      $generatedId
    );

    return $textarea.$js;
  }

  protected function configurePlugins($plugins) {
  	foreach ($plugins as $plugin) {
  		$methodName = '_configure'.$plugin;
  		if (method_exists($this, $methodName)) {
  			$this->$methodName();
  		}
  	}
  	// TODO: enentualne konflikty tutaj rozstrzygnij
  }

  protected function _configureStylist() {
		$cssArray = sfContext::getInstance()->getResponse()->getStylesheets();
		$cssArray = $this->getOption('css_files')? array_merge($cssArray, $this->getOption('css_files')): $cssArray;
		foreach ($cssArray as $file => $options) {
				$this->_additionalConfig .= 'xinha_config.stylistLoadStylesheet("'.stylesheet_path($file).'");'."\n";
		}
  }

	protected function _configureExtendedFileManager() {
        $sf_request = sfContext::getInstance()->getRequest();
        $path       = $sf_request->getUriPrefix().$sf_request->getRelativeUrlRoot ();

        $IMConfig = array(
            'images_dir' => sfConfig::get('sf_upload_dir').'/images',
            'images_url' => $path.'/uploads/images',
            'files_dir' => sfConfig::get('sf_upload_dir').'/assets',
            'files_url' => $path.'/uploads/assets',
            'thumbnail_prefix' => 't_',
            'thumbnail_dir' => 'thumbnail',
            'resized_prefix' => 'resized_',
            'resized_dir' => 'resized',
            'tmp_prefix' => '_tmp',
            'max_filesize_kb_image' => 2000,
            // maximum size for uploading files in 'insert image' mode (2000 kB here)

            'max_filesize_kb_link' => 5000,
            // maximum size for uploading files in 'insert link' mode (5000 kB here)

            // Maximum upload folder size in Megabytes.
            // Use 0 to disable limit
            'max_foldersize_mb' => 0,

            'allowed_image_extensions' => array('jpg','gif','png'),
            'allowed_link_extensions' => array('jpg','gif','pdf','ip','txt',
                                                         'psd','png','html','swf',
                                                         'xml','xls')
      );
      $backendStuff = $this->xinha_to_js( current($this->xinha_pass_to_php_backend($IMConfig, 'Xinha:BackendKey', true)));
 
      $this->_additionalConfig .=
'if (xinha_config.ExtendedFileManager) {
  with (xinha_config.ExtendedFileManager) {
    backend_data = ' . $backendStuff . "; \n
  }
}";

  }

  function xinha_pass_to_php_backend($Data, $KeyLocation = 'Xinha:BackendKey', $ReturnPHP = FALSE)
  {

    $bk = array();
    $bk['data']       = serialize($Data);

    @session_start();
    if(!isset($_SESSION[$KeyLocation]))
    {
      $_SESSION[$KeyLocation] = uniqid('Key_');
    }

    $bk['session_name'] = session_name();
    $bk['key_location'] = $KeyLocation;
    $bk['hash']         =
      function_exists('sha1') ?
        sha1($_SESSION[$KeyLocation] . $bk['data'])
      : md5($_SESSION[$KeyLocation] . $bk['data']);


    // The data will be passed via a postback to the
    // backend, we want to make sure these are going to come
    // out from the PHP as an array like $bk above, so
    // we need to adjust the keys.
    $backend_data = array();
    foreach($bk as $k => $v)
    {
      $backend_data["backend_data[$k]"] = $v;
    }

    // The session_start() above may have been after data was sent, so cookies
    // wouldn't have worked.
    $backend_data[session_name()] = session_id();

    if($ReturnPHP)
    {
      return array('backend_data' => $backend_data);
    }
    else
    {
      echo 'backend_data = ' . xinha_to_js($backend_data) . "; \n";
    }
  }

  /** Convert PHP data structure to Javascript */

  function xinha_to_js($var, $tabs = 0)
  {
    if(is_numeric($var))
    {
      return $var;
    }

    if(is_string($var))
    {
      return "'" . $this->xinha_js_encode($var) . "'";
    }

    if(is_array($var))
    {
      $useObject = false;
      foreach(array_keys($var) as $k) {
          if(!is_numeric($k)) $useObject = true;
      }
      $js = array();
      foreach($var as $k => $v)
      {
        $i = "";
        if($useObject) {
          if(preg_match('#^[a-zA-Z]+[a-zA-Z0-9]*$#', $k)) {
            $i .= "$k: ";
          } else {
            $i .= "'$k': ";
          }
        }
        $i .= $this->xinha_to_js($v, $tabs + 1);
        $js[] = $i;
      }
      if($useObject) {
          $ret = "{\n" . $this->xinha_tabify(implode(",\n", $js), $tabs) . "\n}";
      } else {
          $ret = "[\n" . $this->xinha_tabify(implode(",\n", $js), $tabs) . "\n]";
      }
      return $ret;
    }

    return 'null';
  }

  /** Like htmlspecialchars() except for javascript strings. */

  function xinha_js_encode($string)
  {
    static $strings = "\\,\",',%,&,<,>,{,},@,\n,\r";

    if(!is_array($strings))
    {
      $tr = array();
      foreach(explode(',', $strings) as $chr)
      {
        $tr[$chr] = sprintf('\x%02X', ord($chr));
      }
      $strings = $tr;
    }

    return strtr($string, $strings);
  }


  /** Used by plugins to get the config passed via
  *   xinha_pass_to_backend()
  *  returns either the structure given, or NULL
  *  if none was passed or a security error was encountered.
  */

  function xinha_read_passed_data()
  {
   if(isset($_REQUEST['backend_data']) && is_array($_REQUEST['backend_data']))
   {
     $bk = $_REQUEST['backend_data'];
     session_name($bk['session_name']);
     @session_start();
     if(!isset($_SESSION[$bk['key_location']])) return NULL;

     if($bk['hash']         ===
        function_exists('sha1') ?
          sha1($_SESSION[$bk['key_location']] . $bk['data'])
        : md5($_SESSION[$bk['key_location']] . $bk['data']))
     {
       return unserialize(ini_get('magic_quotes_gpc') ? stripslashes($bk['data']) : $bk['data']);
     }
   }

   return NULL;
  }

  /** Used by plugins to get a query string that can be sent to the backend
  * (or another part of the backend) to send the same data.
  */

  function xinha_passed_data_querystring()
  {
   $qs = array();
   if(isset($_REQUEST['backend_data']) && is_array($_REQUEST['backend_data']))
   {
     foreach($_REQUEST['backend_data'] as $k => $v)
     {
       $v =  ini_get('magic_quotes_gpc') ? stripslashes($v) : $v;
       $qs[] = "backend_data[" . rawurlencode($k) . "]=" . rawurlencode($v);
     }
   }

   $qs[] = session_name() . '=' . session_id();
   return implode('&', $qs);
  }


  /** Just space-tab indent some text */
  function xinha_tabify($text, $tabs)
  {
    if($text)
    {
      return str_repeat("  ", $tabs) . preg_replace('/\n(.)/', "\n" . str_repeat("  ", $tabs) . "\$1", $text);
    }
  }

  /** Return upload_max_filesize value from php.ini in kilobytes (function adapted from php.net)**/
  function upload_max_filesize_kb()
  {
    $val = ini_get('upload_max_filesize');
    $val = trim($val);
    $last = strtolower($val{strlen($val)-1});
    switch($last)
    {
      // The 'G' modifier is available since PHP 5.1.0
      case 'g':
        $val *= 1024;
      case 'm':
        $val *= 1024;
   }
   return $val;
	}
  function curPageURL() {
    $pageURL = 'http';
    if (isset($_SERVER["HTTPS"]))
    {
      $pageURL .= "s";
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
  }
}

