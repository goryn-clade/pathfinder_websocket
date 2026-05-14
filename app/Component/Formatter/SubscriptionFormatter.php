<?php
/**
 * Created by PhpStorm.
 * User: exodu
 * Date: 31.03.2018
 * Time: 13:09
 */

namespace Exodus4D\Socket\Component\Formatter;


class SubscriptionFormatter{

    /**
     * group charactersData by systemId based on their current 'log' data
     * @param array<string, mixed> $charactersData
     * @return array<string, mixed>
     */
    static function groupCharactersDataBySystem(array $charactersData) : array {
        $data = [];
        foreach($charactersData as $characterData){
            // check if characterData has an active log (active system for character)
            $systemId = 0;
            if(isset($characterData['log']['system']['id'])){
                $systemId = (int)$characterData['log']['system']['id'];
            }

            if( !isset($data[$systemId]) ){
                $systemData = (object)[];
                $systemData->id = $systemId;
                $data[$systemId] = $systemData;
            }

            $data[$systemId]->user[] = $characterData;
        }
        $data = array_values($data);

        return $data;
    }

}