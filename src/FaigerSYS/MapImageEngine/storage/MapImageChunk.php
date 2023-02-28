<?php

namespace FaigerSYS\MapImageEngine\storage;

use InvalidArgumentException;
use pocketmine\color\Color;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\ClientboundMapItemDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\MapImage as PMMapImage;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use ReflectionClass;

class MapImageChunk{

	/**
	 * Current cache API version
	 */
	public const CACHE_API = 4;

	private int $width;
	private int $height;

	private int $map_id;

	public BinaryStream $data;

	public function __construct(int $width, int $height, string $data, int $map_id = null){
		if($width < 0 || $height < 0){
			throw new InvalidArgumentException('Width/height must be greater than 0');
		}
		if($width * $height * 4 !== strlen($data)){
			throw new InvalidArgumentException('Given data does not match with given width and height');
		}

		$this->width = $width;
		$this->height = $height;
		$this->map_id = $map_id ?? Entity::nextRuntimeId();
		$this->data = new BinaryStream($data);
	}

	/**
	 * Returns map image chunk map ID
	 *
	 * @return int
	 */
	public function getMapId() : int{
		return $this->map_id;
	}

	/**
	 * Sets map image chunk map ID
	 */
	public function setMapId(int $map_id) : void{
		$this->map_id = $map_id;
	}

	/**
	 * Returns map image chunk width
	 *
	 * @return int
	 */
	public function getWidth() : int{
		return $this->width;
	}

	/**
	 * Returns map image chunk height
	 *
	 * @return int
	 */
	public function getHeight() : int{
		return $this->height;
	}

	/**
	 * Returns RGBA color at specified position
	 *
	 * @param int $x
	 * @param int $y
	 *
	 * @return int
	 */
	public function getRGBA(int $x, int $y) : int{
		$this->data->setOffset($this->getStartOffset($x, $y));
		return (int) $this->data->getInt();
	}

	/**
	 * Sets RBGA color at specified position
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 */
	public function setRGBA(int $x, int $y, int $color) : void{
		$pos = $this->getStartOffset($x, $y);
		$buffer = $this->data->getBuffer();
		$buffer[$pos++] = chr($color & 0xff);
		$buffer[$pos++] = chr($color >> 8 & 0xff);
		$buffer[$pos++] = chr($color >> 16 & 0xff);
		$buffer[$pos] = chr($color >> 24 & 0xff);
		$this->setBuffer($buffer);
	}

	/**
	 * Returns ABGR color at specified position
	 *
	 * @param int $x
	 * @param int $y
	 *
	 * @return int
	 */
	public function getABGR(int $x, int $y) : int{
		$this->data->setOffset($this->getStartOffset($x, $y));
		return (int) $this->data->getLInt() & 0xffffffff;
	}

	/**
	 * Sets ABGR color at specified position
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 */
	public function setABGR(int $x, int $y, int $color) : void{
		$pos = $this->getStartOffset($x, $y);
		$buffer = $this->data->getBuffer();
		$buffer[$pos++] = chr($color >> 24 & 0xff);
		$buffer[$pos++] = chr($color >> 16 & 0xff);
		$buffer[$pos++] = chr($color >> 8 & 0xff);
		$buffer[$pos] = chr($color & 0xff);
		$this->setBuffer($buffer);
	}

	/**
	 * Returns array of Color objects
	 *
	 * @return array
	 */
	public function toArrayColor() : array{
		$colors = [];
		$this->data->rewind();
		for($y = 0; $y < $this->height; $y++){
			for($x = 0; $x < $this->width; $x++){
				$color = $this->data->getInt();
				$colors[$y][$x] = new Color($color >> 24 & 0xff, $color >> 16 & 0xff, $color >> 8 & 0xff, $color & 0xff);
			}
		}
		return $colors;
	}

	/**
	 * Returns RGBA colors array
	 *
	 * @return array
	 */
	public function toArrayRGBA() : array{
		$colors = [];
		$this->data->rewind();
		for($y = 0; $y < $this->height; $y++){
			for($x = 0; $x < $this->width; $x++){
				$colors[$y][$x] = (int) $this->data->getInt();
			}
		}

		return $colors;
	}

	/**
	 * Returns pretty RGBA colors array
	 *
	 * @return array
	 */
	public function toArrayPrettyRGBA() : array{
		$colors = [];
		$this->data->rewind();
		for($y = 0; $y < $this->height; $y++){
			for($x = 0; $x < $this->width; $x++){
				$colors[$y][$x] = [
					'r' => $this->data->getByte(),
					'g' => $this->data->getByte(),
					'b' => $this->data->getByte(),
					'a' => $this->data->getByte()
				];
			}
		}

		return $colors;
	}

	/**
	 * Returns ABGR colors array
	 *
	 * @return array
	 */
	public function toArrayABGR() : array{
		$colors = [];
		$this->data->rewind();
		for($y = 0; $y < $this->height; $y++){
			for($x = 0; $x < $this->width; $x++){
				$colors[$y][$x] = $this->data->getLInt() & 0xffffffff;
			}
		}

		return $colors;
	}

	/**
	 * Returns RGBA colors binary
	 *
	 * @return string
	 */
	public function toBinaryRGBA() : string{
		return $this->data->getBuffer();
	}

	/**
	 * Generates map image packet
	 *
	 * @param int|null $map_id
	 *
	 * @return ClientboundMapItemDataPacket
	 */
	public function generateMapImagePacket(int $map_id = null) : ClientboundMapItemDataPacket{
		$pk = new ClientboundMapItemDataPacket;
		$pk->mapId = $map_id ?? $this->map_id;
		$pk->scale = 0;
		$pk->colors = new PMMapImage($this->toArrayColor());
		$pk->origin = new BlockPosition(0, 0, 0);
		
		return $pk;
	}

	/**
	 * Generates custom map image packet
	 *
	 * @param int|null $map_id
	 * @param bool     $use_cache
	 *
	 * @return ClientboundMapItemDataPacket
	 */
	public function generateCustomMapImagePacket(int $map_id = null, bool $use_cache = true) : ClientboundMapItemDataPacket{
		return $this->generateMapImagePacket($map_id);
	}

	/**
	 * Creates a new map image chunk from the RGBA color array
	 *
	 * @param int   $width
	 * @param int   $height
	 * @param array $colors
	 *
	 * @return MapImageChunk
	 */
	public static function fromArrayRGBA(int $width, int $height, array $colors) : MapImageChunk{
		if($width < 0 || $height < 0){
			throw new InvalidArgumentException('Width/height must be greater than 0');
		}

		$data = new BinaryStream;

		for($y = 0; $y < $height; $y++){
			for($x = 0; $x < $width; $x++){
				if(!is_int($colors[$y][$x] ?? null)){
					throw new InvalidArgumentException('Color is corrupted on [X: ' . $x . ', Y: ' . $y . ']');
				}

				$data->putInt($colors[$y][$x]);
			}
		}

		return new MapImageChunk($width, $height, $data->getBuffer());
	}

	/**
	 * Creates a new map image chunk from the ABGR colors array
	 *
	 * @param int   $width
	 * @param int   $height
	 * @param array $colors
	 *
	 * @return MapImageChunk
	 */
	public static function fromArrayABGR(int $width, int $height, array $colors) : MapImageChunk{
		if($width < 0 || $height < 0){
			throw new InvalidArgumentException('Width/height must be greater than 0');
		}

		$data = new BinaryStream;

		for($y = 0; $y < $height; $y++){
			for($x = 0; $x < $width; $x++){
				if(!is_int($colors[$y][$x] ?? null)){
					throw new InvalidArgumentException('Color is corrupted on [X: ' . $x . ', Y: ' . $y . ']');
				}

				$data->putLInt($colors[$y][$x]);
			}
		}

		return new MapImageChunk($width, $height, $data->getBuffer());
	}

	/**
	 * Creates a new map image chunk with the specified color
	 *
	 * @param int $width
	 * @param int $height
	 * @param int $fill_color
	 *
	 * @return MapImageChunk
	 */
	public static function generateImageChunk(int $width, int $height, int $fill_color = 0) : MapImageChunk{
		if($width < 0 || $height < 0){
			throw new InvalidArgumentException('Width/height must be greater than 0');
		}

		return new MapImageChunk($width, $height, str_repeat(Binary::writeInt($fill_color), $width * $height));
	}

	private function getStartOffset(int $x, int $y) : int{
		if($x < 0 || $y < 0){
			throw new InvalidArgumentException('X/Y must be greater than 0');
		}
		if($x >= $this->width){
			throw new InvalidArgumentException('X cannot be greater than width');
		}
		if($y >= $this->height){
			throw new InvalidArgumentException('Y cannot be greater than height');
		}

		return ($y * $this->width) + $x;
	}

	public function __clone(){
		$this->map_id = Entity::nextRuntimeId();
	}

	private function setBuffer(string $buffer) : void{
		$r = new ReflectionClass(BinaryStream::class);
		$p = $r->getProperty("buffer");
		$p->setAccessible(true);
		$p->setValue($this->data, $buffer);
		$p = $r->getProperty("offset");
		$p->setAccessible(true);
		$p->setValue($this->data->getOffset());
	}
}
