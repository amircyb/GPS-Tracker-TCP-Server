<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GpsTcpServer extends Command
{
    protected $signature = 'gps:server';
    protected $description = 'Start the TCP server to receive GPS data';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        ini_set('max_execution_time', 0);
        ini_set('default_socket_timeout', -1);
        ini_set('max_input_time', -1);

        $socket = stream_socket_server("tcp://0.0.0.0:1234", $errno, $errstr);

        if (!$socket) {
            $this->error("Unable to create socket: $errstr ($errno)");
            return;
        }

        $this->info("TCP server started on port 1234");

        while (true) {
            try {
                $conn = stream_socket_accept($socket);

                if ($conn) {
                    $this->info("New connection established");

                    // Keep the connection open for reading
                    while (true) {
                        $payload = fread($conn, 1500);  // Read up to 1500 bytes

                        if ($payload === false || $payload === '') {
                            $this->info("Connection closed by client");
                            break;
                        }

                        // Log raw payload in hex for debugging
                        $this->info("Raw payload: " . bin2hex($payload));

                        // Process the data
                        $this->processGpsData($conn, bin2hex($payload));
                    }

                    fclose($conn);
                    $this->info("Connection closed");
                }
            } catch (\Exception $e) {
                $this->error("Error processing connection: " . $e->getMessage());
            }
        }

        fclose($socket);
    }


    public function processGpsData($conn, $payload)
    {


        $isGt06 = false;
        $rec = $payload;
        $imei = '';



        $tempString = $rec."";
        //verifica se é gt06
        $retTracker = $this->hex_dump($rec."");
        $arCommands = explode(' ',trim($retTracker));
        if(count($arCommands) > 0){
            if($arCommands[0].$arCommands[1] == '7878'){
                $isGt06 = true;
            }
        }


        if($isGt06) {
            $arCommands = explode(' ', $retTracker);
            $tmpArray = array_count_values($arCommands);

            $count = $tmpArray[78];
            $count = $count / 2;

            $tmpArCommand = array();
            if($count >= 1){
                $ar = array();
                for($i=0;$i<count($arCommands);$i++){
                    if(strtoupper(trim($arCommands[$i]))=="78" && isset($arCommands[$i+1]) && strtoupper(trim($arCommands[$i+1])) == "78"){
                        $ar = array();
                        if(strlen($arCommands[$i]) == 4){
                            $ar[] = substr($arCommands[$i],0,2);
                            $ar[] = substr($arCommands[$i],2,2);
                        } else {
                            $ar[] = $arCommands[$i];
                        }
                    } elseif(isset($arCommands[$i+1]) && strtoupper(trim($arCommands[$i+1]))=="78" && strtoupper(trim($arCommands[$i]))!="78" && isset($arCommands[$i+2]) && strtoupper(trim($arCommands[$i+2]))=="78"){
                        if(strlen($arCommands[$i]) == 4){
                            $ar[] = substr($arCommands[$i],0,2);
                            $ar[] = substr($arCommands[$i],2,2);
                        } else {
                            $ar[] = $arCommands[$i];
                        }
                        $tmpArCommand[] = $ar;
                    } elseif($i == count($arCommands)-1){
                        if(strlen($arCommands[$i]) == 4){
                            $ar[] = substr($arCommands[$i],0,2);
                            $ar[] = substr($arCommands[$i],2,2);
                        } else {
                            $ar[] = $arCommands[$i];
                        }
                        $tmpArCommand[] = $ar;
                    } else {
                        if(strlen($arCommands[$i]) == 4){
                            $ar[] = substr($arCommands[$i],0,2);
                            $ar[] = substr($arCommands[$i],2,2);
                        } else {
                            $ar[] = $arCommands[$i];
                        }
                    }
                }



                for($i=0;$i<count($tmpArCommand);$i++) {
                    $arCommands = $tmpArCommand[$i];
                    $sizeData = $arCommands[2];

                    $protocolNumber = strtoupper(trim($arCommands[3]));

                    if($protocolNumber == '01'){
                        $imei = '';

                        for($i=4; $i<12; $i++){
                            $imei = $imei.$arCommands[$i];
                        }
                        $imei = substr($imei,1,15);
                        $conn_imei = $imei;

                        //$this->abrirArquivoLog($imei);

                        $sendCommands = array();

                        $send_cmd = '78 78 05 01 '.strtoupper($arCommands[12]).' '.strtoupper($arCommands[13]);

                        //atualizarBemSerial($conn_imei, strtoupper($arCommands[12]).' '.strtoupper($arCommands[13]));

                        $newString = '';
                        $newString = chr(0x05).chr(0x01).$rec[12].$rec[13];
                        $crc16 = $this->GetCrc16($newString,strlen($newString));
                        $crc16h = floor($crc16/256);
                        $crc16l = $crc16 - $crc16h*256;

                        $crc = dechex($crc16h).' '.dechex($crc16l);

                        //$crc = crcx25('05 '.$protocolNumber.' '.strtoupper($arCommands[12]).' '.strtoupper($arCommands[13]));

                        //$crc = str_replace('ffff','',dechex($crc));

                        //$crc = strtoupper(substr($crc,0,2)).' '.strtoupper(substr($crc,2,2));

                        $send_cmd = $send_cmd. ' ' . $crc . ' 0D 0A';

                        $sendCommands = explode(' ', $send_cmd);


                        $this->info(" Imei: $imei Got: ".implode(" ",$arCommands));
                        //printLog($fh, date("d-m-y h:i:sa") . " Imei: $imei Sent: $send_cmd Length: ".strlen($send_cmd));

                        $send_cmd = '';
                        for($i=0; $i<count($sendCommands); $i++){
                            $send_cmd .= chr(hexdec(trim($sendCommands[$i])));
                        }

                        //fwrite($conn, $send_cmd);
                        //echo "<br/>". bin2hex($send_cmd);
                        //socket_send($socket, $send_cmd, strlen($send_cmd), 0);
                    } else if ($protocolNumber == '12' || $protocolNumber == '22') {
                        //printLog($fh, date("d-m-y h:i:sa") . " Imei: $imei Got: ".implode(" ",$arCommands));
                        $dataPosition = hexdec($arCommands[4]).'-'.hexdec($arCommands[5]).'-'.hexdec($arCommands[6]).' '.hexdec($arCommands[7]).':'.hexdec($arCommands[8]).':'.hexdec($arCommands[9]);
                        $gpsQuantity = $arCommands[10];
                        $lengthGps = hexdec(substr($gpsQuantity,0,1));
                        $satellitesGps = hexdec(substr($gpsQuantity,1,1));
                        $latitudeHemisphere = '';
                        $longitudeHemisphere = '';
                        $speed = hexdec($arCommands[19]);



                        //78 78 1f 12 0e 05 1e 10 19 05 c4 01 2c 74 31 03 fa b2 b2 07 18 ab 02 d4 0b 00 b3 00 24 73 00 07 5b 59 0d 0a
                        //18 ab
                        //0001100010101011
                        //01 2b af f6
                        //03 fa 37 88
                        if(isset($arCommands[20]) && isset($arCommands[21])){
                            $course = decbin(hexdec($arCommands[20]));
                            while(strlen($course) < 8) $course = '0'.$course;

                            $status = decbin(hexdec($arCommands[21]));
                            while(strlen($status) < 8) $status = '0'.$status;
                            $courseStatus = $course.$status;

                            $gpsRealTime = substr($courseStatus, 2,1) == '0' ? 'F':'D';
                            $gpsPosition = substr($courseStatus, 3,1) == '0' ? 'F':'L';
                            //$gpsPosition = 'S';
                            $gpsPosition == 'F' ? 'S' : 'N';
                            $latitudeHemisphere = substr($courseStatus, 5,1) == '0' ? 'S' : 'N';
                            $longitudeHemisphere = substr($courseStatus, 4,1) == '0' ? 'E' : 'W';
                        }
                        $latHex = hexdec($arCommands[11].$arCommands[12].$arCommands[13].$arCommands[14]);
                        $lonHex = hexdec($arCommands[15].$arCommands[16].$arCommands[17].$arCommands[18]);

                        $latitudeDecimalDegrees = ($latHex*90)/162000000;
                        $longitudeDecimalDegrees = ($lonHex*180)/324000000;

                        $latitudeHemisphere == 'S' && $latitudeDecimalDegrees = $latitudeDecimalDegrees*-1;
                        $longitudeHemisphere == 'W' && $longitudeDecimalDegrees = $longitudeDecimalDegrees*-1;
                        if(isset($arCommands[30]) && isset($arCommands[30])){
                            //atualizarBemSerial($conn_imei, strtoupper($arCommands[30]).' '.strtoupper($arCommands[31]));
                        } else {
                            $this->info('Imei: '.$imei.' Got:'.$retTracker);
                        }
                        $dados = array($gpsPosition,
                            $latitudeDecimalDegrees,
                            $longitudeDecimalDegrees,
                            $latitudeHemisphere,
                            $longitudeHemisphere,
                            $speed,
                            $imei,
                            $dataPosition,
                            'tracker',
                            '',
                            'S',
                            $gpsRealTime);

                        //tratarDados($dados);
                        $this->info("Lat/Long: ".$latitudeDecimalDegrees.", ".$longitudeDecimalDegrees);
                    }
                }


            }
        }
    }


    function GetCrc16($pData, $nLength) {
        $crctab16 = array(
            0X0000, 0X1189, 0X2312, 0X329B, 0X4624, 0X57AD, 0X6536, 0X74BF,
            0X8C48, 0X9DC1, 0XAF5A, 0XBED3, 0XCA6C, 0XDBE5, 0XE97E, 0XF8F7,
            0X1081, 0X0108, 0X3393, 0X221A, 0X56A5, 0X472C, 0X75B7, 0X643E,
            0X9CC9, 0X8D40, 0XBFDB, 0XAE52, 0XDAED, 0XCB64, 0XF9FF, 0XE876,
            0X2102, 0X308B, 0X0210, 0X1399, 0X6726, 0X76AF, 0X4434, 0X55BD,
            0XAD4A, 0XBCC3, 0X8E58, 0X9FD1, 0XEB6E, 0XFAE7, 0XC87C, 0XD9F5,
            0X3183, 0X200A, 0X1291, 0X0318, 0X77A7, 0X662E, 0X54B5, 0X453C,
            0XBDCB, 0XAC42, 0X9ED9, 0X8F50, 0XFBEF, 0XEA66, 0XD8FD, 0XC974,
            0X4204, 0X538D, 0X6116, 0X709F, 0X0420, 0X15A9, 0X2732, 0X36BB,
            0XCE4C, 0XDFC5, 0XED5E, 0XFCD7, 0X8868, 0X99E1, 0XAB7A, 0XBAF3,
            0X5285, 0X430C, 0X7197, 0X601E, 0X14A1, 0X0528, 0X37B3, 0X263A,
            0XDECD, 0XCF44, 0XFDDF, 0XEC56, 0X98E9, 0X8960, 0XBBFB, 0XAA72,
            0X6306, 0X728F, 0X4014, 0X519D, 0X2522, 0X34AB, 0X0630, 0X17B9,
            0XEF4E, 0XFEC7, 0XCC5C, 0XDDD5, 0XA96A, 0XB8E3, 0X8A78, 0X9BF1,
            0X7387, 0X620E, 0X5095, 0X411C, 0X35A3, 0X242A, 0X16B1, 0X0738,
            0XFFCF, 0XEE46, 0XDCDD, 0XCD54, 0XB9EB, 0XA862, 0X9AF9, 0X8B70,
            0X8408, 0X9581, 0XA71A, 0XB693, 0XC22C, 0XD3A5, 0XE13E, 0XF0B7,
            0X0840, 0X19C9, 0X2B52, 0X3ADB, 0X4E64, 0X5FED, 0X6D76, 0X7CFF,
            0X9489, 0X8500, 0XB79B, 0XA612, 0XD2AD, 0XC324, 0XF1BF, 0XE036,
            0X18C1, 0X0948, 0X3BD3, 0X2A5A, 0X5EE5, 0X4F6C, 0X7DF7, 0X6C7E,
            0XA50A, 0XB483, 0X8618, 0X9791, 0XE32E, 0XF2A7, 0XC03C, 0XD1B5,
            0X2942, 0X38CB, 0X0A50, 0X1BD9, 0X6F66, 0X7EEF, 0X4C74, 0X5DFD,
            0XB58B, 0XA402, 0X9699, 0X8710, 0XF3AF, 0XE226, 0XD0BD, 0XC134,
            0X39C3, 0X284A, 0X1AD1, 0X0B58, 0X7FE7, 0X6E6E, 0X5CF5, 0X4D7C,
            0XC60C, 0XD785, 0XE51E, 0XF497, 0X8028, 0X91A1, 0XA33A, 0XB2B3,
            0X4A44, 0X5BCD, 0X6956, 0X78DF, 0X0C60, 0X1DE9, 0X2F72, 0X3EFB,
            0XD68D, 0XC704, 0XF59F, 0XE416, 0X90A9, 0X8120, 0XB3BB, 0XA232,
            0X5AC5, 0X4B4C, 0X79D7, 0X685E, 0X1CE1, 0X0D68, 0X3FF3, 0X2E7A,
            0XE70E, 0XF687, 0XC41C, 0XD595, 0XA12A, 0XB0A3, 0X8238, 0X93B1,
            0X6B46, 0X7ACF, 0X4854, 0X59DD, 0X2D62, 0X3CEB, 0X0E70, 0X1FF9,
            0XF78F, 0XE606, 0XD49D, 0XC514, 0XB1AB, 0XA022, 0X92B9, 0X8330,
            0X7BC7, 0X6A4E, 0X58D5, 0X495C, 0X3DE3, 0X2C6A, 0X1EF1, 0X0F78,
        );
        $fcs = 0xffff;
        $i = 0;
        while ($nLength > 0) {
            $fcs = ($fcs >> 8) ^ $crctab16[($fcs ^ ord($pData[$i])) & 0xff]; // Changed {$i} to [$i]
            $nLength--;
            $i++;
        }
        return ~$fcs & 0xffff;
    }



    function hex_dump($data, $newline="\n")
    {
        static $from = '';
        static $to = '';

        static $width = 50; # number of bytes per line

        static $pad = '.'; # padding for non-visible characters

        if ($from==='')
        {
            for ($i=0; $i<=0xFF; $i++)
            {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
            }
        }

        $hex = str_split(($data), $width*2);
        $chars = str_split(strtr($data, $from, $to), $width);

        $offset = 0;
        $retorno = '';
        foreach ($hex as $i => $line)
        {
            $retorno .= implode(' ', str_split($line,2));
            $offset += $width;
        }
        return $retorno;
        //sprintf($retorno);
    }

}
