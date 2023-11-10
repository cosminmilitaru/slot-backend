<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as Controller;
use Illuminate\Support\Facades\Validator;

class SlotController extends Controller
{
    public function spin(Request $request){
        // check the stake variable
        $validator = Validator::make($request->all(), [
            'stake' => 'required' ,
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }
        $stake = $request->stake; // stake value // if it should not be required we can set default 1 $stake = $request->stake ?? 1
        $bufferLines = []; //all lines that are created with the config lines positions
        $reels = []; //reels values
        $payLine = []; //paylines with the details
        $statusLine = []; //symbol conversion details
        $win = 0; // win value
        $data = $this->getConfigSlot(); // get config json as object
        //spin the reels with the config reels values
        foreach ($data->reels[0] as $reel ){
            $startIndex = mt_rand(0, count($reel) - 3); // because we need 3 reels value we need to lower the counter with 3 and return a random index
            $reels[] = array_slice($reel, $startIndex, 3); // slice the startIndex value and the next 2 values
        }
        $lines = (array_map(null , ...$reels)); // convert the reels to lines
        foreach ($data->lines as $index=>$line){ //loop the config lines so we can create bufferLines
            $newline = [];
            foreach ($line as $key=>$value) {
                $newline[] = $lines[$value][$key];
            }
            $checkedLine = $this->checkPaylines($newline); // check the line for special symbols and length
            $checkedLine['lineIndex'] = $index;
            $checkedLine['line'] = $newline;
            if(!empty($checkedLine['swap']))$statusLine[] = $checkedLine; // save the checked value for symbol conversion details
            if($checkedLine['length'] >= 3 ) { // if the line consecutive symbols length is bigger than 3 than save the payline and increment the win
                foreach ($data->pays as $pay){ // loop the pays data and save the win
                    if($pay[0] == $checkedLine['symbol'] && $pay[1] == $checkedLine['length']){
                        $checkedLine['rawPayment'] = $pay[2];
                        $checkedLine['stake'] = $stake;
                        $checkedLine['payment'] = $pay[2]*$stake;
                        $win = $win+ ($pay[2]*$stake);
                    }
                }
                $payLine[] = $checkedLine;
            }
            $bufferLines[] = $newline;

        }
        return response()->json([
            'screen' => array(
                'reels' => $reels, //reels
                'lines' => $lines, //lines
                'bufferLines' => $bufferLines, // all 10 lines
                'stake' => $stake , // stake
                'win' => $win, // total win
                'countWinLines' => count($payLine) ,
                'winLine' => $payLine, // paylines
            ),
            'symbol_conversion' => $statusLine , // symbol conversion details
            'payLines' => $payLine // paylines
        ]);
    }

    // get config file and decode the json
    private function getConfigSlot(){
        $data = file_get_contents(base_path('./config.json'));
        return json_decode($data);
    }

    //check line if it have the special symbol and the length of consecutive symbols
    private function checkPaylines($line){
        $swap = []; // save the swap value and position
        $consecutiveCount = 1 ;
        for ($i = 0; $i < count($line) - 1; $i++) {
            // check if the first value is the special symbol and change it with the closest normal symbol
            // we will now know the first normal symbol value and can start to loop throw the line
            if($line[0] == 10){
                foreach ($line as $value){
                    if($value != 10){
                        $line[0] = $value;
                        break;
                    }
                }
                // save the symbol swap vor the conversion details
                $swap[] = [
                    'position' => 0 ,
                    'value' => $line[0]
                ];
            }
            // increment the counter if the symbols are the same
            if ($line[0] === $line[$i + 1]) {
                $consecutiveCount++;
            }elseif($line[$i + 1] == 10) { // if the next symbol is special increment and save the conversion details
                $consecutiveCount++;
                $swap[] = [
                    'position' => $i+1 ,
                    'value' => $line[0]
                ];
            }
            //if there are no consecutive symbols return to stop the loop
            else{
                return [
                    'symbol' => $line[0],
                    'length' => $consecutiveCount ,
                    'swap' => $swap
                ];
            }
        }

        return [
            'symbol' => $line[0],
            'length' => $consecutiveCount ,
            'swap' => $swap
        ];
    }

}
