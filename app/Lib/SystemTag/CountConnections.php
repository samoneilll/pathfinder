<?php

namespace Exodus4D\Pathfinder\Lib\SystemTag;

use Exodus4D\Pathfinder\Model\Pathfinder\ConnectionModel;
use Exodus4D\Pathfinder\Model\Pathfinder\MapModel;
use Exodus4D\Pathfinder\Model\Pathfinder\SystemModel;
use Exodus4D\Pathfinder\Model\Universe\AbstractUniverseModel;
use Exodus4D\Pathfinder\Lib\SystemTag;

class CountConnections implements SystemTagInterface
{
    /**
     * @param SystemModel $targetSystem
     * @param SystemModel $sourceSystem
     * @param MapModel $map
     * @return string|null
     * @throws \Exception
     */
    static function generateFor(SystemModel $targetSystem, SystemModel $sourceSystem, MapModel $map) : ?string
    {
        $targetClass = $targetSystem->security;

        $config = \Exodus4D\Pathfinder\Lib\Config::getPathfinderData('systemtag');
        $homeSystemId = isset($config['HOMESYSTEM']) ? (int)$config['HOMESYSTEM'] : null;

        // Collect existing tags for systems of the same security class (excluding locked/home systems)
        $tags = [];
        foreach ($map->getSystemsData() as $system) {
            if ($system->security === $targetClass && !$system->locked && $system->tag) {
                $tags[] = SystemTag::tagToInt($system->tag);
            }
        }

        // Assign the "s" (static) tag if either endpoint is the home system and the other is C5 or null-sec
        $sourceId = $sourceSystem->systemId;
        $targetId = $targetSystem->systemId;
        $connectionInvolvesHome = $homeSystemId !== null && ($sourceId === $homeSystemId || $targetId === $homeSystemId);
        $otherSystem = ($sourceId === $homeSystemId) ? $targetSystem : $sourceSystem;
        $otherClass  = $otherSystem->security;

        $isStaticCandidate = $connectionInvolvesHome && ($otherClass === 'C5' || $otherClass === '0.0');
        if ($isStaticCandidate && !in_array(18, $tags)) {
            return 's';
        }
        // If $isStaticCandidate but "s" is already taken, fall through to normal counting logic

        // Return 'a' for the first system of this class on the map
        if (empty($tags)) {
            return 'a';
        }

        // Find the first unused tag slot
        sort($tags);
        $tags = array_unique($tags);
        $i = 0;
        while (isset($tags[$i]) && $tags[$i] == $i) {
            $i++;
        }

        return SystemTag::intToTag($i);
    }
}
