<?php


namespace Exodus4D\Pathfinder\Controller\Api\Rest;

use Exodus4D\Pathfinder\Controller\Ccp\Universe;
use Exodus4D\Pathfinder\Lib\Config;

class SystemThera extends AbstractRestController {

    /**
     * cache key for HTTP response
     */
    const CACHE_KEY_THERA_CONNECTIONS               = 'CACHED_THERA_CONNECTIONS';

    /**
     * get Thera connections data from Eve-Scout
     * @param \Base $f3
     */
    public function get(\Base $f3){
        $ttl = 60 * 3;
        if(!$exists = $f3->exists(self::CACHE_KEY_THERA_CONNECTIONS, $connectionsData)){
            $connectionsData = $this->getEveScoutTheraConnections();
            $f3->set(self::CACHE_KEY_THERA_CONNECTIONS, $connectionsData, $ttl);
        }

        $f3->expire(Config::ttlLeft($exists, $ttl));

        $this->out($connectionsData);
    }

    /**
     * get Thera connections data from EveScout API
     * -> map response to Pathfinder format
     * @return array
     */
    protected function getEveScoutTheraConnections() : array {
        $connectionsData = [];
        /**
         * map system data from eveScout response to Pathfinder´s 'system' format
         * @param string $key
         * @param array  $eveScoutConnection
         * @param array  $connectionData
         */
        $enrichWithSystemData = function(string $key, array $eveScoutConnection, array &$connectionData) : void {
            $eveScoutSystem = (array)$eveScoutConnection[$key];
            $universe = new Universe();
            $staticData = $universe->getSystemData($eveScoutSystem['id']);

            $systemData = [
                'id' => (int)$staticData->id,
                'name' => (string)$staticData->name,
                'system_class' => round((float)$staticData->trueSec, 4),
                'constellation' => ['id' => (int)$staticData->constellation->id],
                'region' => [
                    'id' => (int)$staticData->constellation->region->id,
                    'name' => (string)$staticData->constellation->region->name
                ]
            ];
            $connectionData[$key] = $systemData;
        };

        /**
         * @param string $key
         * @param array  $eveScoutConnection
         * @param array  $connectionData
         */
        $enrichWithSignatureData = function(string $key, array $eveScoutConnection, array &$connectionData) : void {
            $eveScoutSignature = (array)$eveScoutConnection[$key];
            $signatureData = [
                'name' => $eveScoutSignature['name'] ? : null,
                'short_name' => str_split($eveScoutSignature['name'],3)[0] ? : null
            ];
            if($key == 'sourceSignature' && $eveScoutConnection['wh_exits_outward']) {
                $signatureData['type'] = ['name' => strtoupper((string)$eveScoutConnection['wh_type'])];
            }
            if($key == 'targetSignature' && !$eveScoutConnection['wh_exits_outware']) {
                $signatureData['type'] = ['name' => strtoupper((string)$eveScoutConnection['wh_type'])];
            }
            $connectionData[$key] = $signatureData;
        };

        /**
         * map wormhole data from eveScout to Pathfinder´s connection format
         * @param array $wormholeData
         * @param array $connectionsData
         */
        $enrichWithWormholeData = function(array $wormholeData, array &$connectionsData) : void {
            $type = [];
            $type[] = 'wh_fresh';

            if($wormholeData['estimatedEol'] <= 4){
                $type[] = 'wh_eol';
            }
            switch($wormholeData['jumpMass']) {
                case "capital":
                    $type[] = 'wh_jump_mass_xl';
                    break;
                case "xlarge":
                    $type[] = 'wh_jump_mass_xl';
                    break;
                case "large":
                    $type[] = 'wh_jump_mass_l';
                    break;
                case "medium":
                    $type[] = 'wh_jump_mass_m';
                    break;
                case "small":
                    $type[] = 'wh_jump_mass_s';
                    break;
                default:
                    break;
            }

            $connectionsData['type'] = $type;
            $connectionsData['estimatedEol'] = $wormholeData['estimatedEol'];
        };

        $eveScoutResponse = $this->getF3()->eveScoutClient()->send('getTheraConnections');
        if(!empty($eveScoutResponse) && !isset($eveScoutResponse['error'])){
            foreach((array)$eveScoutResponse['connections'] as $eveScoutConnection){
                if(
                    $eveScoutConnection['type'] === 'wormhole' &&
                    isset($eveScoutConnection['source']) && isset($eveScoutConnection['target']) &&
                    $eveScoutConnection['source']['id'] === 31000005 // Check it's thera and not a turnur connection
                ){
                    try{
                        $data = [
                            'id' => (int)$eveScoutConnection['id'],
                            'scope' => 'wh',
                            'created' => [
                                'created' => (new \DateTime($eveScoutConnection['created']))->getTimestamp(),
                                'character' => (array)$eveScoutConnection['character']
                            ],
                            'updated' => (new \DateTime($eveScoutConnection['updated']))->getTimestamp()
                        ];
                        $enrichWithWormholeData((array)$eveScoutConnection['wormhole'], $data);
                        $enrichWithSystemData('source', $eveScoutConnection, $data);
                        $enrichWithSystemData('target', $eveScoutConnection, $data);
                        $enrichWithSignatureData('sourceSignature', $eveScoutConnection, $data);
                        $enrichWithSignatureData('targetSignature', $eveScoutConnection, $data);
                        $connectionsData[] = $data;
                    }catch(\Exception $e){
                        // new \DateTime Exception -> skip this data
                    }
                }
            }
        }

        return $connectionsData;
    }
}