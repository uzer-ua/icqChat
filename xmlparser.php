<?php
	class xmlparser{
		private $parser;
		private $source = '';
		private $result = array();
		
		function __construct($source){
			$this->parser = xml_parser_create();
			$this->source = $source;
		}
		
		function parse()
		{
			return true;
		}
	}
?>