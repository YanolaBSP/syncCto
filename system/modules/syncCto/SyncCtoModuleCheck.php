<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto
 * @license    GNU/LGPL 
 * @filesource
 */

// Workaround for missing posix_getpwuid function
if (!function_exists('posix_getpwuid'))
{

	function posix_getpwuid($int)
	{
		return array('name' => $int);
	}

}

/**
 * Class for systemcheck
 */
class SyncCtoModuleCheck extends BackendModule
{

	/**
	 * Template variables
	 */
	protected $strTemplate = 'be_syncCto_check';

	/**
	 * Initialize variables
	 */
	protected $soap				 = false;
	protected $safeModeHack		 = false;
	protected $isWindows			 = false;
	protected $folderPermissions	 = array();
	protected $filePermissions = array();

	/**
	 * Constructor
	 * 
	 * @param DataContainer $objDc 
	 */
	public function __construct(DataContainer $objDCA = null)
	{
		parent::__construct($objDCA);
		$this->loadLanguageFile('tl_syncCto_check');
	}

	public function generate()
	{
		return parent::generate();
	}

	protected function compile()
	{
		$this->import('BackendUser', 'User');
		$this->Template->script = $this->Environment->script;

		$this->Template->checkPhpConfiguration		 = $this->checkPhpConfiguration($this->getPhpConfigurations());
		$this->Template->checkPhpFunctions			 = $this->checkPhpFunctions($this->getPhpFunctions());
		$this->Template->checkSpecialPhpFunctions	 = $this->checkSpecialPhpFunctions($this->getPhpFunctions());
		$this->Template->syc_version				 = $GLOBALS['SYC_VERSION'];
	}

	/**
	 * Get a list with informations about some php vars
	 * 
	 * @return array
	 */
	public function getPhpConfigurations()
	{
		return array(
			'safe_mode'				 => ini_get('safe_mode'),
			'max_execution_time'	 => ini_get('max_execution_time'),
			'memory_limit'			 => $this->getSize(ini_get('memory_limit')),
			'register_globals'		 => ini_get('register_globals'),
			'file_uploads'			 => ini_get('file_uploads'),
			'upload_max_filesize'	 => $this->getSize(ini_get('upload_max_filesize')),
			'post_max_size'			 => $this->getSize(ini_get('post_max_size')),
			'max_input_time'		 => ini_get('max_input_time'),
			'default_socket_timeout' => ini_get('default_socket_timeout'),
			'suhosin'				 => $this->checkSuhosin()
		);
	}
    
    public function checkSuhosin()
    {
        $blnIsActive = false;

        // Check as apache modules
        try
        {
            if (in_array('mod_security', @apache_get_modules()))
            {
                $blnIsActive = true;
            }
        }
        catch (Exception $exc)
        {
            
        }
        
        // Check php ini
        if(ini_get('suhosin.session.max_id_length'))
        {
            $blnIsActive = true;
        }
        
        // Check patch
        if(constant("SUHOSIN_PATCH"))
        {
            $blnIsActive = true;
        }
        
        return $blnIsActive;
    }

    /**
     * Get a list with informations about the required functions
     * 
     * @return array
     */
	public function getPhpFunctions()
	{
		return array(
			'fsockopen'		 => function_exists("fsockopen"),
			'zip_archive'	 => @class_exists('ZipArchive'),
			'gmp'			 => extension_loaded('gmp'),
			'bcmath'		 => extension_loaded('bcmath'),
			'xmlwriter'		 => @class_exists('XMLWriter'),
			'xmlreader'		 => @class_exists('XMLReader'),
			'mcrypt'		 => extension_loaded('mcrypt'),
		);
	}

	private function getSize($strValue)
	{
		return (int) str_replace(array("M", "G"), array("000000", "000000000"), $strValue);
	}

	/**
	 * Return true if the Safe Mode Hack is required
	 * 
	 * @return boolean
	 */
	public function requiresSafeModeHack()
	{
		return $this->safeModeHack;
	}

	/**
	 * Check all PHP extensions and return the result as string
	 * 
	 * @return string
	 */
	public function checkPhpConfiguration($arrConfigurations)
	{
		$return = '<table width="100%" cellspacing="0" cellpadding="0" class="extensions" summary="">';
		$return .= '<colgroup>';
		$return .= '<col width="25%" />';
		$return .= '<col width="5%" />';
		$return .= '<col width="15%" />';
		$return .= '<col width="*" />';
		$return .= '</colgroup>';
		$return .= '<tr>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['parameter'] . '</th>';
		$return .= '<th class="dot" style="width:1%;">&#149;</th>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['value'] . '</th>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['description'] . '</th>';
		$return .= '</tr>';

		// Safe mode
		$safe_mode	 = $arrConfigurations['safe_mode'];
		$ok			 = ($safe_mode == '' || $safe_mode == 0 || $safe_mode == 'Off');
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['safemode'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($safe_mode ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['safemode'][1] . '</td>';
		$return .= '</tr>';

		if ($safe_mode)
		{
			$this->safeModeHack = true;
		}

		// Maximum execution time
		$max_execution_time	 = $arrConfigurations['max_execution_time'];
		$ok					 = ($max_execution_time >= 30 || $max_execution_time == 0);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['met'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . $max_execution_time . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['met'][1] . '</td>';
		$return .= '</tr>';

		// Memory limit
		$memory_limit	 = $arrConfigurations['memory_limit'];
		$ok				 = (intval($memory_limit) >= 128000000);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['memory_limit'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . $this->getReadableSize($memory_limit) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['memory_limit'][1] . '</td>';
		$return .= '</tr>';

		// Register globals
		$register_globals	 = $arrConfigurations['register_globals'];
		$ok					 = ($register_globals == '' || $register_globals == 0 || $register_globals == 'Off');
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['register_globals'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($register_globals ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['register_globals'][1] . '</td>';
		$return .= '</tr>';

		// File uploads
		$file_uploads	 = $arrConfigurations['file_uploads'];
		$ok				 = ($file_uploads == 1 || $file_uploads == 'On');
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['file_uploads'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($file_uploads ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['file_uploads'][1] . '</td>';
		$return .= '</tr>';

		// Upload maximum filesize
		$upload_max_filesize = $arrConfigurations['upload_max_filesize'];
		$ok					 = (intval($upload_max_filesize) >= 8000000);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['umf'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . $this->getReadableSize($upload_max_filesize) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['umf'][1] . '</td>';
		$return .= '</tr>';

		// Post maximum size
		$post_max_size	 = $arrConfigurations['post_max_size'];
		$ok				 = (intval($post_max_size) >= 8000000);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['pms'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . $this->getReadableSize($post_max_size) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['pms'][1] . '</td>';
		$return .= '</tr>';

		// Maximum input time
		$max_input_time	 = $arrConfigurations['max_input_time'];
		$ok				 = ($max_input_time == '-1' || (intval($max_input_time) >= 60));
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['mit'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . $max_input_time . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['mit'][1] . '</td>';
		$return .= '</tr>';

		// Default socket timeout
		$default_socket_timeout	 = $arrConfigurations['default_socket_timeout'];
		$ok						 = (intval($default_socket_timeout) >= 32);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['dst'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . $default_socket_timeout . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['dst'][1] . '</td>';
		$return .= '</tr>';

		// suhosin
		$suhosin = $arrConfigurations['suhosin'];
		$ok		 = ($suhosin == false);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['suhosin'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($suhosin ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['suhosin'][1] . '</td>';
		$return .= '</tr>';

		$return .= '</table>';

		return $return;
	}

	/**
	 * Check all PHP function/class and return the result as string
	 * 
	 * @return string
	 */
	public function checkPhpFunctions($arrFunctions)
	{
		$return = '<table width="100%" cellspacing="0" cellpadding="0" class="extensions" summary="">';
		$return .= '<colgroup>';
		$return .= '<col width="25%" />';
		$return .= '<col width="5%" />';
		$return .= '<col width="15%" />';
		$return .= '<col width="*" />';
		$return .= '</colgroup>';
		$return .= '<tr>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['parameter'] . '</th>';
		$return .= '<th class="dot" style="width:1%;">&#149;</th>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['value'] . '</th>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['description'] . '</th>';
		$return .= '</tr>';

		// fsockopen
		$fsockopen	 = $arrFunctions['fsockopen'];
		$ok			 = ($fsockopen == true);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['fsocket'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($fsockopen ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['fsocket'][1] . '</td>';
		$return .= '</tr>';

		// ZipArchive
		$zip_archive = $arrFunctions['zip_archive'];
		$ok			 = ($zip_archive == true);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['zip_archive'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($zip_archive ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['zip_archive'][1] . '</td>';
		$return .= '</tr>';

		// mcrypt
		$mcrypt	 = $arrFunctions['mcrypt'];
		$ok		 = ($mcrypt == true);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['mcrypt'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($mcrypt ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['mcrypt'][1] . '</td>';
		$return .= '</tr>';

		// XMLWriter
		$xmlwriter	 = $arrFunctions['xmlwriter'];
		$ok			 = ($xmlwriter == true);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['xmlwriter'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($xmlwriter ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['xmlwriter'][1] . '</td>';
		$return .= '</tr>';

		// XMLReader
		$xmlreader	 = $arrFunctions['xmlreader'];
		$ok			 = ($xmlreader == true);
		$return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['xmlreader'][0] . '</td>';
		$return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
		$return .= '<td class="value">' . ($xmlreader ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
		$return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['xmlreader'][1] . '</td>';
		$return .= '</tr>';

		$return .= '</table>';

		return $return;
	}

	public function checkSpecialPhpFunctions($arrFunctions)
	{
		$return .= '</table>';

		$return .= '<table width="100%" cellspacing="0" cellpadding="0" class="extensions" summary="">';
		$return .= '<colgroup>';
		$return .= '<col width="25%" />';
		$return .= '<col width="5%" />';
		$return .= '<col width="15%" />';
		$return .= '<col width="*" />';
		$return .= '</colgroup>';
		$return .= '<tr>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['parameter'] . '</th>';
		$return .= '<th class="dot" style="width:1%;">&#149;</th>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['value'] . '</th>';
		$return .= '<th>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['description'] . '</th>';
		$return .= '</tr>';
        
		$gmp    = $arrFunctions['gmp'];
        $bcmath = $arrFunctions['bcmath'];

        // bcmath
        if ($bcmath == true || ($bcmath == false && $gmp == false))
        {
            $ok = ($bcmath == true);
            $return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
            $return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['bcmath'][0] . '</td>';
            $return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
            $return .= '<td class="value">' . ($bcmath ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
            $return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['bcmath'][1] . '</td>';
            $return .= '</tr>';
        }

        // gmp
        if ($gmp == true || ($bcmath == false && $gmp == false))
        {
            $ok = ($gmp == true);
            $return .= '<tr class="' . ($ok ? 'ok' : 'warning') . '">';
            $return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['gmp'][0] . '</td>';
            $return .= '<td class="dot">' . ($ok ? '&nbsp;' : '&#149;') . '</td>';
            $return .= '<td class="value">' . ($gmp ? $GLOBALS['TL_LANG']['tl_syncCto_check']['on'] : $GLOBALS['TL_LANG']['tl_syncCto_check']['off']) . '</td>';
            $return .= '<td>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['gmp'][1] . '</td>';
            $return .= '</tr>';
        }

        $return .= '</table>';

        return $return;
	}

}

?>