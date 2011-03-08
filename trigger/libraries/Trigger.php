<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Trigger
{
	/**
	 * Context
	 *
	 * Array that determines the string output
	 * when exiting the function
	 */
	public $context 				= array();

	// --------------------------------------------------------------------------
	
	/**
	 * Out
	 *
	 * Contains the string we want to return
	 */
	public $out					= TRUE;

	// --------------------------------------------------------------------------
	
	/**
	 * Bracketed Variable
	 *
	 * If there is a bracketed variable it will be
	 * found and put into this variable.
	 */
	public $variable			= FALSE;

	// --------------------------------------------------------------------------
	
	/**
	 * System Commands
	 *
	 * Array of commands that the system
	 * understands and parses
	 */
	public $system_commands 		= array('drivers');

	// --------------------------------------------------------------------------

	function __construct()
	{
		$this->EE =& get_instance();
		
		// Define some things
		define('TRIGGER_BUFFER', '------------------');
		define('VARS_LEFT', '(');
		define('VARS_RIGHT', ')');
		define('VAR_SEP', ',');	
	}

	// --------------------------------------------------------------------------

	/**
	 * Parses & executes the line input from the controller
	 *
	 * @access	public
	 * @param	line
	 * @param	bool
	 */
	function process_line( $line, $hey = '' )
	{
		$this->line 	= $line;

		// -------------------------------------
		// Set 0 context to 'ee'
		// -------------------------------------

		$this->context[0] = 'ee';
			
		// -------------------------------------
		// Explode and Clean Line
		// -------------------------------------
		
		$parts = explode(":", $line, 3);
		
		foreach( $parts as $key => $part ):
		
			$parts[$key] = trim($part);
		
		endforeach;

		// -------------------------------------
		// Easy out for "root"
		// -------------------------------------
		// Root is special because it just means
		// get me out of here and forget it.
		// -------------------------------------
		
		if( isset($parts[2]) && $parts[2] == 'root' ):
		
			$this->context = array('ee');
			
			return;
					
		endif;

		// -------------------------------------
		// Load our Variables
		// -------------------------------------
		
		$this->EE->load->library('Vars');

		// -------------------------------------
		// Insert Variables
		// -------------------------------------
		// Replaces {} curly braced variables
		// With system variables 
		// -------------------------------------
		
		$this->system_var_methods = get_class_methods($this->EE->vars);
		
		foreach( $this->system_var_methods as $method ):
		
			$variable_val = $this->EE->vars->$method();
			
			foreach( $parts as $key => $part ):
			
				$parts[$key] = str_replace(LD.$method.RD, $variable_val, $part);
			
			endforeach;
		
		endforeach;
		
		// -------------------------------------
		// Check Segment Numbers
		// -------------------------------------

		$total_segments = count($parts);

		// -------------------------------------
		// Single Segment Processing
		// -------------------------------------
	
		if( $total_segments == 2 ):
		
			$segment = $parts[1];
			
			// Is this a system variable?
			if($this->_is_variable($segment)):
			
				return $this->out;
			
			endif;
	
			// See if we have a bracketed [] variable
			// If we do, we set it to a variables
			$segment = $this->_extract_var($segment);
						
			// Maybe a system command?
			if($this->_is_system_command($segment, array('drivers'))):
			
				return $this->out;
			
			endif;
	
			if( ! $this->_load_driver($segment) ):

				// Not a driver?
				// Well, looks like the command could not be
				// understood. Bummer.
				return "unknown command";
			
			else:
				
				// We will go quiety with no errors.
				return;
			
			endif;
		
		elseif( $total_segments == 3 ):

		// -------------------------------------
		// Double Segment Processing
		// -------------------------------------

			$driver_slug = $parts[1];
			
			$segment = $parts[2];

			// We know there has to be a driver.
			// If there isn't throw dem flagz up!			
			if( !$this->_load_driver( $driver_slug ) ):

				if($this->out != ''):
				
					return $this->out;
				
				else:
				
					return "$driver_slug driver not found";
				
				endif;
			
			endif;
			
			// Find the bracketed variable
			$segment = $this->_extract_var($segment);
			
			// Set the context to the driver. Other functions
			// will set it back if need be.
			$this->context = array( 'ee', $this->driver->driver_slug );
			
			// -------------------------------------
			// Replace driver variables
			// -------------------------------------
			
			if( $this->driver->has_vars === TRUE ):
			
				// TODO
			
			endif;

			// -------------------------------------

			// Could this be a system command?
			if($this->_is_system_command($segment)):
			
				return $this->out;
			
			endif;
		
			// Perhaps a driver variable?
			if($this->_is_variable( $segment, $this->driver )):
			
				return $this->out;
			
			endif;
			
			// Could very well possibly be a singular command.
			if($this->_is_singular_command( $segment )):
			
				return $this->out;
			
			endif;

			// This is an unknown command. Just write a log
			// entry and get out of here.
			write_log($this->line, "unknown command");
			return "unknown command";
		
		// End Segment Processing
		endif;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Is this a Singular Command?
	 *
	 * @access	private
	 * @param	string
	 * @return	void
	 */
	private function _is_singular_command( $segment )
	{
		$call = str_replace(" ", "_", $segment);
		
		// All commands have a prefix so we can use things like list
		// and new without gettin' out shit all mixed up
		$call = '_comm_'.strtolower($call);
		
		// Check to see if the command exists. Issue error if it doesn't.
		// Otherwise, run the command.
		
		if( method_exists($this->driver, $call) ):
		
			$msg = $this->driver->$call($this->variable);
	
			write_log($this->line, $msg);
			
			$this->out = $msg;
			
			return TRUE;
		
		endif;
		
		return FALSE;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Is This a System Command?
	 *
	 * Checks to see if the input is a system command
	 * and if it is allowed to be run in this spot
	 *
	 * @access	private
	 * @param	string
	 * @param	[array]
	 * @return	mixed
	 */
	private function _is_system_command( $segment, $allowed = array() )
	{
		if( empty($allowed) ):
		
			$allowed = $this->system_commands;
		
		endif;
		
		// Does this command exist and is allowed?
		// If so, run it.
		
		if( in_array($segment, $allowed) ):
		
			$call = 'system_'.$segment;
		
			$this->$call($this->variable);
			
			return TRUE;
		
		endif;
		
		return FALSE;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * See if something is a variable and return the value if needed
	 *
	 * @access	private
	 * @param	string
	 * @param	obj
	 * @return	string
	 */
	private function _is_variable($segment = '', $driver_obj = FALSE)
	{
		if( $driver_obj === FALSE ):

			// -------------------------------------
			// No Driver Obj
			// -------------------------------------
			// No driver object means it could be
			// a system variable
			// -------------------------------------
			
			if( in_array($segment, $this->system_var_methods) ):
			
				$this->context = array('ee');
			
				$this->out = $this->EE->vars->$segment();
				
				return TRUE;
						
			endif;

		else:
		
			// -------------------------------------
			// Driver Variable
			// -------------------------------------
			// Could be a driver variable
			// -------------------------------------
		
			// TODO
		
		endif;
		
		return FALSE;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Loads a driver
	 *
	 * Checks to see if there is a driver folder before loading
	 * all of the necessary items.
	 *
	 * @access	private
	 * @param	string
	 * @return	mixed
	 */
	private function _load_driver( $driver_slug )
	{
		$driver_folder = PATH_THIRD.'trigger/drivers/';
	
		if( is_dir($driver_folder.$driver_slug) ):

			// -------------------------------------
			// Load driver file
			// -------------------------------------
			
			$driver_file = $driver_folder.$driver_slug.'/'.$driver_slug.'.driver.php';
			
			if( file_exists($driver_file) ):

				@require_once($driver_file);
	
				$driver_class = 'Driver_'.$driver_slug;
				
				$this->driver = new $driver_class();
			
			else:
			
				// We can't go on without a driver file
				$this->out = "missing driver file";
				
				return FALSE;
			
			endif;

			// -------------------------------------
			// See if we have commands
			// -------------------------------------
			// Commands are just methods in the
			// driver file.
			// -------------------------------------
			
			if(get_class_methods($this->driver)):
			
				$this->driver->has_commands = TRUE;
			
			else:
				
				$this->driver->has_commands = FALSE;
			
			endif;
		
			// -------------------------------------
			// Load driver language
			// -------------------------------------
			
			$lang_file = $driver_folder.$driver_slug.'/language/'.$this->EE->config->item('deft_lang').'/lang.'.$driver_slug.'.php';
			
			if( ! file_exists($lang_file) ):
			
				// Looks like there is no language file. That's no good!
				
				$error = "no language file found for $driver_slug driver";
			
				write_log($this->line, $error);
	
				$this->out = $error;
				
				return FALSE;
				
			else:
			
				@include($lang_file);
			
			endif;
	
			// -------------------------------------
			// Load master language & merge
			// -------------------------------------
			
			@include(PATH_THIRD . 'trigger/language/'.$this->EE->config->item('deft_lang').'/lang.trigger.php');
			
			$this->driver->lang 		= array_merge($driver_lang, $lang);
			
			// Copy for wider use
			$this->EE->trigger_lang		= $this->driver->lang;
						
			// Set up some class variables
			$this->driver->driver_name 	= $this->driver->lang['driver_name'];
			$this->driver->driver_desc 	= $this->driver->lang['driver_desc'];

			// -------------------------------------
			// Load Variables
			// -------------------------------------
			
			$vars_file = $driver_folder.$driver_slug.'/'.$driver_slug.'.vars.php';
			
			if( file_exists($vars_file) ):
			
				@require_once($vars_file);
				
				$vars_class = 'Vars_'.$driver_slug;
				
				$this->driver->vars = new $vars_class();

				$this->driver->has_vars = TRUE;
			
			else:
				
				$this->driver->has_vars = FALSE;
			
			endif;			
						
			// -------------------------------------
			// Set driver to driver context position
			// -------------------------------------
			
			$this->context[1] = $driver_slug;
			
			return TRUE;
			
		endif;
		
		return FALSE;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Find a bracketed variable
	 *
	 * @access	public
	 * @param	string
	 * @return 	string
	 */
	public function _extract_var($string)
	{
		if(strpos($string, VARS_LEFT) !== FALSE && strpos($string, VARS_RIGHT) !== FALSE):
		
			$open 	= strpos($string, VARS_LEFT, 0) + strlen(VARS_LEFT);
			$close 	= strpos($string, VARS_RIGHT, 0);
			
			$tmp_var = trim(substr($string, $open, $close-$open));
			
			// See if it is an array of values
			$vars = explode(VAR_SEP, $tmp_var);
			
			if(count($vars)==1):
			
				// Just a string.
				$this->variable = $tmp_var;
			
			else:
			
				$this->variable = $vars;
			
			endif;
			
			return trim(str_replace(VARS_LEFT.$tmp_var.VARS_RIGHT, '', $string));
		
		endif;
		
		return $string;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Output a response by bypassing the control panel
	 *
	 * @access	private
	 * @param	string
	 * @return	void
	 */	
	function output_response( $output )
	{
		$this->EE->output->enable_profiler(FALSE);

		if ($this->EE->config->item('send_headers') == 'y'):
		
			@header('Content-Type: text/html; charset=UTF-8');	
		
		endif;
		
		if(trim($output) == ''):
		
			exit($this->output_context());
		
		else:

			exit($output."\n".$this->output_context());
		
		endif;
	}

	// --------------------------------------------------------------------------
	
	/**
	 * Outputs the context in the correct format
	 *
	 * @access	public
	 * @param	array
	 * @return	string
	 */
	function output_context()
	{
		$output = null;
		
		// Just root if none provided
		if( empty($this->context) ):
		
			$this->context = array('ee');
		
		endif;
		
		// Output the context
		foreach( $this->context as $cont ):
		
			$output .= $cont . " : ";
		
		endforeach;

		return $output;
	}

	// --------------------------------------------------------------------------
	// System Commands	
	// --------------------------------------------------------------------------	
	
	/**
	 * Drivers
	 *
	 * Lists out the drivers that are installed
	 */
	public function system_drivers()
	{
		// TODO
	}

}

/* Trigger.php */