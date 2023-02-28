<?php

namespace FaigerSYS\MapImageEngine\storage;

use pocketmine\network\mcpe\protocol\ClientboundMapItemDataPacket;
use function spl_object_hash;

class ImageStorage{

    public const R_OK = 0;
    public const R_ALREADY_REGISTERED = 1;
    public const R_UUID_EXISTS = 2;
    public const R_INVALID_NAME = 3;
    public const R_NAME_EXISTS = 4;

    /** @var MapImage[] */
    private array $images = [];

    /** @var string[] */
    private array $hashes = [];

    /** @var string[] */
    private array $names = [];

    /** @var ClientboundMapItemDataPacket[] */
    private array $mapDataCaches = [];

    /**
     * Registers new image
     *
     * @param MapImage    $image
     * @param bool        $cache_packets
     * @param string|null $name
     *
     * @return int
     */
    public function registerImage(MapImage $image, bool $cache_packets = true, string $name = null) : int{
        $spl_hash = spl_object_hash($image);
        if(isset($this->images[$spl_hash])){
            return self::R_ALREADY_REGISTERED;
        }

        $hash = $image->getHashedUUID();
        if(isset($this->hashes[$hash])){
            return self::R_UUID_EXISTS;
        }

        if($name !== null){
            $name = str_replace(' ', '_', $name);
            if($name === ''){
                return self::R_INVALID_NAME;
            }
            if(isset($this->names[$name])){
                return self::R_NAME_EXISTS;
            }
            $this->names[$name] = $hash;
        }

        $this->images[$spl_hash] = $image;
        $this->hashes[$hash] = $spl_hash;

        if($cache_packets){
            $this->regeneratePacketsCache($image);
        }

        return self::R_OK;
    }

    /**
     * Unregisters image
     *
     * @param MapImage $image
     */
    public function unregisterImage(MapImage $image) : void{
        $spl_hash = spl_object_hash($image);
        if(!isset($this->images[$spl_hash])){
            return;
        }
        $hash = $image->getHashedUUID();

        $this->removePacketsCache($image);

        foreach($this->names as $name => $o_spl_hash){
            if($spl_hash === $o_spl_hash){
                unset($this->names[$name]);
            }
        }
        foreach($this->names as $name => $o_spl_hash){
            if($spl_hash === $o_spl_hash){
                unset($this->names[$name]);
            }
        }
        unset($this->hashes[$hash], $this->images[$spl_hash]);
    }

    /**
     * Regenerates map image packets cache
     *
     * @param MapImage|null $image
     * @param int|null      $chunk_x
     * @param int|null      $chunk_y
     */
    public function regeneratePacketsCache(MapImage $image = null, int $chunk_x = null, int $chunk_y = null) : void{
        if($image === null){
            $this->mapDataCaches = [];
            foreach($this->images as $image){
                $this->regeneratePacketsCache($image);
            }
        }else{
            if(!isset($this->images[spl_object_hash($image)])){
                return;
            }
            if($chunk_x === null && $chunk_y === null){
                foreach($image->getChunks() as $chunks){
                    foreach($chunks as $chunk){
                        $this->mapDataCaches[$chunk->getMapId()] = $chunk->generateMapImagePacket();
                    }
                }
            }else{
                $chunk = $image->getChunk($chunk_x, $chunk_y);
                if($chunk !== null){
                    $this->mapDataCaches[$chunk->getMapId()] = $chunk->generateMapImagePacket();
                }
            }
        }
    }

    /**
     * Removes map image packets from cache
     *
     * @param MapImage $image
     * @param int|null $chunk_x
     * @param int|null $chunk_y
     */
    public function removePacketsCache(MapImage $image, int $chunk_x = null, int $chunk_y = null) : void{
        if(!isset($this->images[spl_object_hash($image)])){
            return;
        }
        if($chunk_x === null && $chunk_y === null){
            foreach($image->getChunks() as $chunks){
                foreach($chunks as $chunk){
                    unset($this->mapDataCaches[$chunk->getMapId()]);
                }
            }
        }else{
            $chunk = $image->getChunk($chunk_x, $chunk_y);
            if($chunk !== null){
                unset($this->mapDataCaches[$chunk->getMapId()]);
            }
        }
    }

    /**
     * Removes map image packet with specified map ID
     *
     * @param int $map_id
     */
    public function removePacketCache(int $map_id) : void{
        unset($this->mapDataCaches[$map_id]);
    }

    /**
     * Returns map image with specified UUID hash
     *
     * @param string $uuid_hash
     *
     * @return MapImage|null
     */
    public function getImage(string $uuid_hash) : ?MapImage{
        return $this->images[$this->hashes[$uuid_hash] ?? null] ?? null;
    }

    /**
     * Returns map image with specified name
     *
     * @param string $name
     *
     * @return MapImage|null
     */
    public function getImageByName(string $name) : ?MapImage{
        return $this->getImage($this->names[str_replace(' ', '_', $name)] ?? '');
    }

    /**
     * Returns all of map images
     *
     * @return MapImage[]
     */
    public function getImages() : array{
        return $this->images;
    }

    /**
     * Returns all of map images that have name
     *
     * @return MapImage[]
     */
    public function getNamedImages() : array{
        return array_map(function($hash){
            return $this->getImage($hash);
        }, $this->names);
    }

    /**
     * Returns cached map image packet
     *
     * @param int $map_id
     *
     * @return ?ClientboundMapItemDataPacket
     */
    public function getCachedPacket(int $map_id) : ?ClientboundMapItemDataPacket{
        if(isset($this->mapDataCaches[$map_id])){
            return clone $this->mapDataCaches[$map_id];
        }
        return null;
    }

    /**
     * @phpstan-return array<int, ClientboundMapItemDataPacket>
     * @return ClientboundMapItemDataPacket[]
     */
    public function getCachedPackets() : array{
        return $this->mapDataCaches;
    }
}
