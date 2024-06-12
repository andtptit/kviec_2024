<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EntityCollection extends ResourceCollection
{
	public string $entityResource;
	
	/**
	 * EntityCollection constructor.
	 *
	 * @param $controllerName
	 * @param $resource
	 */
	public function __construct($controllerName, $resource)
	{
		parent::__construct($resource);
		
		$this->entityResource = str($controllerName)->replaceLast('Controller', 'Resource')->toString();
		if (!str_starts_with($this->entityResource, '\\')) {
			$this->entityResource = '\\' . __NAMESPACE__ . '\\' . $this->entityResource;
		}
	}
	
	/**
	 * Transform the resource into an array.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @return array
	 */
	public function toArray($request): array
	{
		return [
			'data' => $this->collection->transform(function ($entity) {
				return new $this->entityResource($entity);
			}),
		];
	}
}
