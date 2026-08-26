<?php
/**
 * AiExtractionService Class
 * 
 * Uses the Google Gemini API to extract structured disaster variables from natural language SMS.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/../config/gemini.php';

class AiExtractionService {
    /**
     * Extract structured parameters from message body using LLM
     * 
     * @param string $messageBody
     * @return array
     */
    public static function extract($messageBody) {
        $config = GeminiConfig::get();
        $apiKey = $config['api_key'];

        // If API key is not configured, run local mock heuristic processor for SIH testing
        if (empty($apiKey)) {
            return self::getMockExtraction($messageBody, "Demo Mode: Gemini API Key Not Configured");
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

        // Force structured JSON output and define properties
        $promptText = "Analyze this emergency SMS message:\n\"" . $messageBody . "\"\n\n"
                    . "Extract details and return a VALID JSON object matching this schema. Return ONLY JSON, do not include markdown blocks or any text besides the JSON:\n"
                    . "{\n"
                    . "  \"disaster_type\": \"flood|fire|earthquake|medical|accident|landslide|cyclone|other\",\n"
                    . "  \"location\": \"Extracted location name or null\",\n"
                    . "  \"people_count\": integer,\n"
                    . "  \"injured_count\": integer,\n"
                    . "  \"help_required\": \"comma separated list of needs, e.g. medical, rescue, food, water or null\",\n"
                    . "  \"priority\": \"LOW|MEDIUM|HIGH|CRITICAL\",\n"
                    . "  \"person_name\": \"Extracted person name or null\",\n"
                    . "  \"confidence\": float (between 0.0 and 1.0)\n"
                    . "}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local setup environments

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return self::getMockExtraction($messageBody, "Gemini API Connection Failed (HTTP " . $httpCode . ")");
        }

        try {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $jsonString = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                
                // Strip possible markdown blocks if Gemini added them despite formatting instruction
                $jsonString = preg_replace('/^```(?:json)?\s*|```\s*$/i', '', $jsonString);
                
                $extractedData = json_decode($jsonString, true);
                
                if ($extractedData) {
                    self::addCoordinates($extractedData);
                    return $extractedData;
                }
            }
        } catch (Exception $e) {
            // Keep going to fallback mock
        }

        return self::getMockExtraction($messageBody, "Gemini JSON parsing exception");
    }

    /**
     * Fallback processor parsing natural text using rules for mock outputs
     */
    private static function getMockExtraction($messageBody, $reason) {
        $cleanBody = strtolower($messageBody);
        
        $location = null;
        if (preg_match('/\b(?:at|in|near|near to|around|village|colony|area)\s+([A-Z][a-zA-Z\s]{2,15}\b)/', $messageBody, $matches)) {
            $location = trim($matches[1]);
        } else {
            $location = "Unknown (Extracted via rules)";
        }
        
        $peopleCount = 1;
        if (preg_match('/(\d+)\s*(?:people|persons|trapped|stuck|lives)/i', $messageBody, $matches)) {
            $peopleCount = (int)$matches[1];
        }

        $injuredCount = 0;
        if (preg_match('/(\d+)\s*(?:injured|hurt|wounded|casualties)/i', $messageBody, $matches)) {
            $injuredCount = (int)$matches[1];
        }

        $disaster = "unknown";
        $disasters = ['flood', 'fire', 'earthquake', 'accident', 'landslide', 'cyclone', 'medical'];
        foreach ($disasters as $d) {
            if (stripos($cleanBody, $d) !== false) {
                $disaster = $d;
                break;
            }
        }

        $data = [
            'disaster_type' => $disaster,
            'location' => $location,
            'people_count' => $peopleCount,
            'injured_count' => $injuredCount,
            'help_required' => 'rescue, medical',
            'priority' => ($injuredCount > 0 || $peopleCount > 5) ? 'HIGH' : 'MEDIUM',
            'person_name' => null,
            'confidence' => 0.40,
            'note' => $reason
        ];

        self::addCoordinates($data);
        return $data;
    }

    /**
     * Helper to mock coordinates in India for simulation map plotting
     */
    private static function addCoordinates(&$data) {
        if (empty($data['location'])) {
            $data['latitude'] = null;
            $data['longitude'] = null;
            return;
        }

        $loc = strtolower($data['location']);
        $regions = [
            'rampur' => [28.8124, 79.0250],
            'lucknow' => [26.8467, 80.9462],
            'delhi' => [28.6139, 77.2090],
            'mumbai' => [19.0760, 72.8777],
            'chennai' => [13.0827, 80.2707],
            'kolkata' => [22.5726, 88.3639],
            'patna' => [25.5941, 85.1376],
            'guwahati' => [26.1158, 91.7086],
            'dehradun' => [30.3165, 78.0322],
            'bhuj' => [23.2420, 69.6669],
            'srinagar' => [34.0837, 74.7973]
        ];

        if (array_key_exists($loc, $regions)) {
            $data['latitude'] = $regions[$loc][0];
            $data['longitude'] = $regions[$loc][1];
        } else {
            // North-central India corridor range (near Delhi/Lucknow)
            $data['latitude'] = 26.5 + (mt_rand(-1500, 1500) / 1000);
            $data['longitude'] = 79.5 + (mt_rand(-1500, 1500) / 1000);
        }
    }
}
