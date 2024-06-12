<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HomeSectionResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return array
	 */
	public function toArray($request): array
	{
		$optionName = $this['method'] . 'Op';
		
		return [
			'method'    => $this['method'],
			'data'      => $this['data'],
			'view'      => $this['view'],
			$optionName => $this[$optionName] ?? [],
			'lft'       => $this['lft'],
		];
	}
}
