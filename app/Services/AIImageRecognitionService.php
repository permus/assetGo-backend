<?php

namespace App\Services;

use App\DTO\RecognitionResult;
use App\Models\AIRecognitionHistory;
use Illuminate\Support\Facades\Auth;

class AIImageRecognitionService {
    public function __construct(private OpenAIService $openAI) {}

    public function processImages(array $dataUrls): RecognitionResult {
        // Basic validation
        if (count($dataUrls) < 1 || count($dataUrls) > 5) abort(422, 'Provide 1–5 images');
        foreach ($dataUrls as $u) {
            if (!preg_match('#^data:image/(png|jpeg|jpg);base64,#', $u)) abort(422, 'PNG/JPG only');
            if (strlen($u) > 10_485_760 * 1.38) abort(413, 'Image too large'); // approx check
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
