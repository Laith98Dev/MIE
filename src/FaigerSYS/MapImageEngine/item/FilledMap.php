<?php

namespace FaigerSYS\MapImageEngine\item;

use FaigerSYS\MapImageEngine\MapImageEngine;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

class FilledMap extends Item{

    public const CURRENT_MAP_API = 3;
    public const SUPPORTED_MAP_API = [3];

    public function __construct(){
        parent::__construct(new ItemIdentifier(ItemIds::FILLED_MAP, 0), 'Filled Map');
    }

    public function setNamedTag(CompoundTag $tag) : Item{
        parent::setNamedTag($tag);
        $this->updateMapData();

        return $this;
    }

    public function updateMapData() : void{
        $plugin = MapImageEngine::getInstance();

        $mie_data = $this->getImageData();
        if(!is_array($mie_data)){
            return;
        }

        $map_id = 0;

        $api = $mie_data['api'] ?? -1;
        if(in_array($api, self::SUPPORTED_MAP_API, true)){
            $image = $plugin->getImageStorage()->getImage($mie_data['image_hash']);
            if($image){
                $chunk = $image->getChunk($mie_data['x_block'], $mie_data['y_block']);
                if($chunk){
                    $map_id = $chunk->getMapId();
                }
            }
        }

        $tag = $this->getNamedTag();
        $tag->setString('map_uuid', (string) $map_id);
        parent::setNamedTag($tag);
    }

    public function setImageData(string $image_hash, int $block_x, int $block_y){
        $tag = $this->getNamedTag();
        $tag->setString('mie_data', json_encode([
            'api' => self::CURRENT_MAP_API,
            'image_hash' => $image_hash,
            'x_block' => $block_x,
            'y_block' => $block_y
        ], JSON_THROW_ON_ERROR));
        parent::setNamedTag($tag);

        $this->updateMapData();
    }

    public function getImageData() : array{
        $tag = $this->getNamedTag();
        if($tag->getTag('mie_data') instanceof StringTag){
            return json_decode($tag->getString('mie_data'), true, 512, JSON_THROW_ON_ERROR);
        }
        return [];
    }

    public function getImageHash(){
        return $this->getImageData()['image_hash'] ?? null;
    }

    public function getImageChunkX(){
        return $this->getImageData()['x_block'] ?? null;
    }

    public function getImageChunkY(){
        return $this->getImageData()['y_block'] ?? null;
    }

}
