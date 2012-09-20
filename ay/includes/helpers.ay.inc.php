<?php
/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 1.0.3 (2012 06 19)
 */
function ay()
{
	if(ob_get_level())
	{
		ob_clean();
	}
    
	if(!headers_sent())
	{
		header('Content-Type: text/plain');
	}
	
	if(!AY_DEBUG)
	{
		echo 'The requested content is inaccessible. Please try again later.';
		
		exit;
	}
	
	// unless something went really wrong, $trace[0] will always reference call to ay()
	$trace	= debug_backtrace();
	$trace	= array_shift($trace);
	
	echo 'ay() called in ' . mb_substr($trace['file'], mb_strlen(AY_ROOT)) . ' (' . $trace['line'] . ').' . PHP_EOL . PHP_EOL;
	
	call_user_func_array('var_dump', func_get_args());
	
	echo PHP_EOL . 'Backtrace:' . PHP_EOL . PHP_EOL;
	
	ob_start();
	debug_print_backtrace();
	echo str_replace(AY_ROOT, '', ob_get_clean());
	
	exit;
}

/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 1.3 (2012 08 16)
 */
function ay_message($message, $type = AY_MESSAGE_ERROR)
{
    $_SESSION['ay']['flash']['messages'][$type][]	= $message;
}

/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 2.1
 */
function ay_display_messages()
{
	static $already_displayed	= FALSE;
	
	if($already_displayed)
	{
		return;
	}
	
	$already_displayed			= TRUE;
	
    $return						= '';

    $messages_types				= array
    (
		AY_MESSAGE_NOTICE		=> 'notice',
		AY_MESSAGE_SUCCESS		=> 'success',
		AY_MESSAGE_ERROR		=> 'error',
		AY_MESSAGE_IMPORTANT	=> 'important'
	);
	
    if(!empty($_SESSION['ay']['flash']['messages']))
    {
    	ksort($_SESSION['ay']['flash']['messages']);
		
		foreach($_SESSION['ay']['flash']['messages'] as $type => $messages)
		{
			$return		.= '<ul class="' . $messages_types[$type] . '">';
		
			foreach($messages as $message)
			{
				$message	= preg_replace_callback('/\*([\w\s]+)\*/', function($m){
					return '<span class="highlight">' . $m[1] . '</span>';
				}, $message);
			
				$return	.= '<li>' . $message . '</li>';
			}
			
			$return		.= '</ul>';
		}
    }
    
	return empty($return) ? '' : '<div class="ay-message-placeholder">' . $return . '</div>';
}

/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 1.4 (2012 08 16)
 */
function ay_error_present()
{
	if(empty($_SESSION['ay']['flash']['messages'][AY_MESSAGE_ERROR]))
	{	
		return FALSE;
	}
	else
	{
		return TRUE;
	}
}

/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 1.0.6 (2012 08 16)
 */
function ay_redirect($url = AY_REDIRECT_REFERRER, $message_text = NULL, $message_type = AY_MESSAGE_ERROR)
{
	// If there aren't any error, then clear the persistent user input.
	if(!ay_error_present())
	{
		unset($_SESSION['ay']['flash']['input']);
	}

	if($message_text !== NULL)
    {
		ay_message($message_text, $message_type);
    }
    
    if(headers_sent())
	{
		throw new AyException('Redirect failed. Headers already sent.');
	}

    if($url === AY_REDIRECT_REFERRER)
    {    
		$url	= empty($_SERVER['HTTP_REFERER']) ? constant('AY_URL_' . mb_strtoupper(AY_INTERFACE)) : $_SERVER['HTTP_REFERER'];
    }
    elseif(strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0)
    {
    	$url	= rtrim(constant('AY_URL_' . mb_strtoupper(AY_INTERFACE)), '/') . '/' . $url;
    }

    header('Location: ' . $url);

	exit;
}

/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 1.6.8 (2012 07 02)
 */
function ay_input($name, $label, array $input_options = NULL, array $row_options = NULL, array $return_options = NULL)
{
	global $input;
	
	// all input generated using ay_input() is sent through $_POST['ay'] array
	$name						= strpos($name, '[') !== FALSE ? 'ay[' . strstr($name, '[', TRUE) . ']' . strstr($name, '[') : 'ay[' . $name . ']';
	$original_name_path			= explode('][', mb_substr($name, 3, -1));
	
	// default to a text field if the type is not defined
	if(empty($input_options['type']))
	{
		$input_options['type']	= empty($input_options['options']) ? 'text' : 'select';
	}
	
	$input_options['name']		= $name;
	
	// get input value
	$default_value				= FALSE;
	
	if($input_options['type'] != 'password')
	{		
		$value					= empty($_SESSION['ay']['flash']['input']) ? $input : $_SESSION['ay']['flash']['input'];
		
		foreach($original_name_path as $key)
		{
			if(!is_array($value) || !array_key_exists($key, $value))
			{
				$value	= FALSE;
				
				break;
			}
			
			$value	= $value[$key];
		}
		
		if($value === FALSE || is_array($value))
		{
			$default_value		= TRUE;
		
			$value				= isset($input_options['value']) ? $input_options['value'] : NULL;
		}
	}
	
	// generate attribute string
	$allowed_attributes			= ['name', 'id', 'class', 'maxlength', 'autocomplete'];
	
	if(in_array($input_options['type'], ['text', 'textarea']))
	{
		$allowed_attributes[]	= 'placeholder';
		$allowed_attributes[]	= 'readonly';
	}
	
	$input_attr_str				= '';
	
	foreach($input_options as $k => $v)
	{
		if(in_array($k, $allowed_attributes))
		{
			$input_attr_str	.= ' ' . $k . '="' . $v . '"';
		}			
	}
	
	$str	= array
	(
		'append'	=> '',
		'class'		=> implode('-', $original_name_path) . '-input'
	);
	
	// generate input string
	switch($input_options['type'])
	{
		case 'select':
			if(empty($input_options['options']))
			{
				throw new AyException('Select input is missing options array.');
			}
		
			$option_str	= '';
		
			foreach($input_options['options'] as $v => $l)
			{
				$option_str	.= '<option value="' . $v . '"' . ($value == $v ? ' selected="selected"' : '') . '>' . $l . '</option>';
			}
		
			$str['input']	= '<select ' . $input_attr_str . '>' . $option_str . '</select>';
		
			break;
			
		case 'checkbox':
			if(!array_key_exists('value', $input_options))
			{
				$input_options['value']	= 1;
			}
			else
			{
				$input_options['value']	= (int) $input_options['value'];
			}			
		
			$str['input']				= '<input type="checkbox" value="' . $input_options['value'] . '"' . $input_attr_str . '' . (!$default_value && $input_options['value'] == $value ? ' checked="checked"' : '') . ' />';
			break;
		
		case 'radio':
			if(!array_key_exists('value', $input_options))
			{
				throw new AyException('Radio input is missing value parameter.');
			}
			
			$input_options['value']		= (int) $input_options['value'];
			
			$str['input']				= '<input type="radio" value="' . $input_options['value'] . '"' . $input_attr_str . '' . (!$default_value && $input_options['value'] == $value ? ' checked="checked"' : '') . ' />';

			break;
		
		case 'textarea':
			$str['input']	= '<textarea' . $input_attr_str . '>' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '</textarea>';
			break;
		
		case 'password':
			$str['input']	= '<input type="password" ' . $input_attr_str . ' />';
			break;
		
		default:
			$str['input']	= '<input type="' . $input_options['type'] . '" value="' . filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS) . '"' . $input_attr_str . ' />';
			break;
	}
	
	$return_options['return']	= empty($return_options['return']) ? 'row' : $return_options['return'];
	
	switch($return_options['return'])
	{
		case 'row':			
			if(!empty($row_options['comment']))
			{
				$str['append']	.= '<div class="comment">' . $row_options['comment'] . '</div>';
			}
			
			if(!empty($row_options['class']))
			{
				$str['class']	.= ' ' . $row_options['class'];
			}
			
			if($label === NULL)
			{
				$str['body']	= $str['input'];
			}
			else
			{
				$input_label	= in_array('inverse', explode(' ', $str['class'])) ? $str['input'] . '<div class="label">' . $label . '</div>' : '<div class="label">' . $label . '</div>' . $str['input'];
				
			
				$str['body']	= '<label class="row ' . $str['class']  . ' input-' . $input_options['type'] . '">' . $input_label . '</label>';
			}			
			
			$str['return']	= $str['body'] . ' ' . $str['append'];
			
			break;
			
		case 'input':
			$str['return']	= $str['input'];
			break;
			
		default:
			throw new AyException('ay_input(); unknown return type `' . $return_options['return'] . '`.');
			break;
	}
	
	return $str['return'];
}

/**
 * @author Gajus Kuizinas <g.kuizinas@anuary.com>
 * @version 1.0.4 (2012 06 19); adapted to XHProf.io
 */
function ay_error_exception_handler()
{
	$args	= func_get_args();
	
	if(func_num_args() == 1)
	{
		$data	= array
		(
			'type'				=> NULL,
			'message'			=> $args[0]->getMessage(),
			'file'				=> $args[0]->getFile(),
			'line'				=> $args[0]->getLine()
		);
	}
	else
	{		
		$data	= array
		(
			'type'				=> $args[0],
			'message'			=> $args[1],
			'file'				=> $args[2],
			'line'				=> $args[3]
		);
	}
	
	if(ob_get_level())
	{
		ob_clean();
	}

	if(!headers_sent())
	{
		header('Content-Type: text/plain');
		
		http_response_code(500);
	}
	
	if(AY_DEBUG)
	{
		if($data['type'] === NULL)
		{
			$error_type	= get_class($args[0]);
		}
		else
		{
			switch($data['type'])
			{
				case E_ERROR:
				case E_USER_ERROR:
					$error_type	= 'Fatal run-time error.';
					break;
					
				case E_WARNING:
				case E_USER_WARNING:
					$error_type	= 'Run-time warnings (non-fatal error).';
					break;
					
				case E_NOTICE:
				case E_USER_NOTICE:
					$error_type	= 'Run-time notice.';
					break;
					
				case AY_ERROR_CSS:
					$error_type	= 'LESS error.';
					break;
					
				default:
					$error_type	= 'Unknown ' . $data['type'] . '.';
					break;
			}
		}
		
		
		echo "Type:\t\t{$error_type}\nMessage:\t{$data['message']}\nFile:\t\t{$data['file']}\nLine:\t\t{$data['line']}\nTime:\t\t" . date(AY_FORMAT_DATETIME) . "\n\n";
    	
    	ob_start();
    	debug_print_backtrace();
    	echo str_replace(AY_ROOT, '', ob_get_clean());
	}
	else
	{		
		echo 'Unexpected system behaviour.';
	}
	
	if(function_exists('fastcgi_finish_request'))
	{
		fastcgi_finish_request();
	}
	
	return FALSE;
}