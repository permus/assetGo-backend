<?php

namespace App\Services;

use App\DTO\RecognitionResult;
use App\Models\AIRecognitionHistory;
use Illuminate\Support\Facades\Auth;

class AIImageRecognitionService {
    public function __construct(private OpenAIService $openAI) {}

    public function processImages(array $cleanBase64Images): RecognitionResult {
        // Basic validation
        if (count($cleanBase64Images) < 1 || count($cleanBase64Images) > 5) abort(422, 'Provide 1–5 images');
        
        // Convert clean base64 back to data URLs for OpenAI API
        $dataUrls = [];
        foreach ($cleanBase64Images as $base64) {
            // Validate base64
            if (base64_decode($base64, true) === false) {
                abort(422, 'Invalid base64 image data');
            }
            
            // Convert to data URL (assume JPEG for simplicity)
            $dataUrls[] = 'data:image/jpeg;base64,' . $base64;
        }

        $resultArr = $this->openAI->analyzeImages($dataUrls, $this->analysisPrompt());
        $dto = RecognitionResult::fromArray($resultArr);

        // persist history (optional: store data urls elsewhere and save paths)
        $rec = new AIRecognitionHistory();
        $rec->user_id = Auth::id();
        $rec->company_id = Auth::user()->company_id ?? null;
        $rec->image_paths = []; // if you store, put paths here
        $rec->recognition_result = $dto->toArray();
        $rec->confidence_score = $dto->confidence;
        $rec->save();

        return $dto;
    }

    public function analysisPrompt(): string {
        return "Analyze asset images; extract assetType, manufacturer, model, serialNumber, assetTag, condition, confidence, recommendations, evidence.";
    }
}
