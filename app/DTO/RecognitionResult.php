<?php

namespace App\DTO;

class RecognitionResult {
    public string $assetType;
    public int $confidence; // 0-100
    public ?string $manufacturer;
    public ?string $model;
    public ?string $serialNumber;
    public ?string $assetTag;
    public string $condition; // Excellent | Good | Fair | Poor
    /** @var string[] */
    public array $recommendations = [];
    public array $evidence = [
        'fieldsFound' => [],
        'imagesUsed'  => 0,
        'notes'       => null,
    ];

    public static function fromArray(array $a): self {
        $o = new self();
        $o->assetType = (string) ($a['assetType'] ?? 'Unknown');
        $o->confidence = max(0, min(100, (int) ($a['confidence'] ?? 0)));
        $o->manufacturer = $a['manufacturer'] ?? null;
        $o->model = $a['model'] ?? null;
        $o->serialNumber = $a['serialNumber'] ?? null;
        $o->assetTag = $a['assetTag'] ?? null;
        $o->condition = in_array(($a['condition'] ?? 'Good'), ['Excellent','Good','Fair','Poor'], true) ? $a['condition'] : 'Good';
        $o->recommendations = array_values($a['recommendations'] ?? []);
        $o->evidence = [
            'fieldsFound' => array_values($a['evidence']['fieldsFound'] ?? []),
            'imagesUsed'  => (int) ($a['evidence']['imagesUsed'] ?? 0),
            'notes'       => $a['evidence']['notes'] ?? null,
        ];
        return $o;
    }

    public function toArray(): array {
        return [
            'assetType' => $this->assetType,
            'confidence' => $this->confidence,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'serialNumber' => $this->serialNumber,
            'assetTag' => $this->assetTag,
            'condition' => $this->condition,
            'recommendations' => $this->recommendations,
            'evidence' => $this->evidence,
        ];
    }
}
