<?php
	/*
		Auth : Mansur ATİK
		Edit : 27.08.2017

		Usage:
			URL - File - Part - Exec
			exec | download.php "http://site.com/data.data" "/storage/data.mp4" 7 "/home/user/public_html/api.php success"
	*/

	class Downloader
	{
		public $ranges = [];

		function __construct($url = "",$file = "",$part = 7){
			$this->file = $file;
			$id = md5(microtime());
			$this->work_folder = "/storage/temp/".$id;
			if(!file_exists($this->work_folder)){
				mkdir($this->work_folder);
			}
			$_size = $size = $this->curl_get_file_size($url);
			$leng = floor($size / $part ); $last = 0;
			$i = 0;
			while($_size > 0){
				if($_size > $leng){
					$_size -= $leng; $e = $last + $leng ;
				}else{
					$_size = 0; $e = $size ;
				}
				$this->ranges[] = [
					"file"   => $this->work_folder."/part.".str_pad($i++, 4, "0", STR_PAD_LEFT),
					"url"    => $url,
					"length" => $size,
					"range"  => [ "s" => $last, "e" => $e - 1 ]
				];
				$last = $e;
			}
			$this->start();
		}

		function start(){
			$curls = [];
			$files = [];
			$multi = curl_multi_init();
			foreach($this->ranges as $i =>  $range){
				$files[$i] = fopen ($range["file"], 'w+');
				$curls[$i] = curl_init();
				curl_setopt( $curls[$i], CURLOPT_URL, $range["url"] );
				curl_setopt( $curls[$i], CURLOPT_RETURNTRANSFER, true );
				curl_setopt( $curls[$i], CURLOPT_BINARYTRANSFER, true );
				curl_setopt( $curls[$i], CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $curls[$i], CURLOPT_CONNECTTIMEOUT, 10 );
				curl_setopt( $curls[$i], CURLOPT_BUFFERSIZE, 64000 );
				curl_setopt( $curls[$i], CURLOPT_NOPROGRESS, false );
				curl_setopt( $curls[$i], CURLOPT_FOLLOWLOCATION, true );
				$headers = [
					'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
					'Range: bytes='.$range["range"]["s"].'-'.$range["range"]["e"]
				];
				curl_setopt( $curls[$i], CURLOPT_HTTPHEADER, $headers);
				curl_setopt( $curls[$i], CURLOPT_HEADER, false );
				curl_setopt( $curls[$i], CURLOPT_FILE, $files[$i] );
				curl_multi_add_handle($multi, $curls[$i]);
			}

			$running=null;
			do {
				curl_multi_exec($multi,$running);
			} while ($running > 0);

			foreach($this->ranges as $i => $range){
				curl_multi_remove_handle($multi, $curls[$i]);
				fclose( $files[$i] );
			}
			curl_multi_close($multi);
			$this->bind();
		}

		function bind(){
			$out = fopen($this->file, "w");
			foreach($this->ranges as $i => $range){
				$file = $range["file"];
				$in = fopen($file, "r");
				while ($line = fgets($in)){
					fwrite($out, $line);
				}
				fclose($in);
				unlink($file);
			}
			fclose($out);
			rmdir($this->work_folder);
		}

		function curl_get_file_size( $url ) {
			$result = -1;
			$curl = curl_init( $url );
			curl_setopt( $curl, CURLOPT_NOBODY, true );
			curl_setopt( $curl, CURLOPT_HEADER, true );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $curl, CURLOPT_USERAGENT,'Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Mobile Safari/537.36');
			$data = curl_exec( $curl );
			curl_close( $curl );
			if( $data ) {
				$content_length = "unknown";
				$status = "unknown";
				if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
					$status = (int)$matches[1];
				}
				if( preg_match_all( "/Content-Length: (\d+)/", $data, $matches ) ) {
					$content_length = (int)array_pop($matches[1]);
				}
				if( $status == 200 || ($status > 300 && $status <= 308) ) {
					$result = $content_length;
				}
			}
			return $result;
		}
	}

	$dl = new Downloader(
		$argv[1],
		$argv[2],
		$argv[3]
	);


	shell_exec("/usr/bin/php ". $argv[4] ."  > /dev/null &" );
