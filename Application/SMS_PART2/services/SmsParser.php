<?php
/**
 * SmsParser Service Class
 * 
 * The primary gatekeeper for identifying SOS alerts, parsing coordinates, and structural variables.
 */

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

class SmsParser {
    // Core emergency trigger keywords
    private static $emergencyKeywords = [
        'help', 'sos', 'emergency', 'trapped', 'stuck', 'rescue', 'injured', 
        'accident', 'flood', 'fire', 'earthquake', 'medical', 'tsunami', 
        'landslide', 'drowning', 'disaster', 'casualty', 'trapped in flood'
    ];

    /**
     * Check if a message body indicates an emergency/SOS
     * 
     * @param string $messageBody
     * @return bool
     */
    public static function isEmergency($messageBody) {
        $cleanBody = strtolower(trim($messageBody));
        
        // 1. Direct structured match check
        if (strpos($cleanBody, 'sos|') === 0 || strpos($cleanBody, 'help|') === 0) {
            return true;
        }

        // 2. Keyword trigger scanning
        foreach (self::$emergencyKeywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $messageBody)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Parse structured or natural language SMS message details using local rules
     * 
     * @param string $messageBody
     * @return array
     */
    public static function parse($messageBody) {
        $cleanBody = trim($messageBody);
        
        // Initialize default return structure
        $result = [
            'is_sos' => false,
            'disaster_type' => 'unknown',
            'location' => null,
            'latitude' => null,
            'longitude' => null,
            'people_count' => 1,
            'injured_count' => 0,
            'help_required' => null,
            'priority' => 'CRITICAL',
            'person_name' => null,
            'victim_name' => null,
            'victim_phone' => null,
            'blood_group' => null,
            'age' => null,
            'medical_info' => null,
            'emergency_contact' => null,
            'home_address' => null,
            'map_url' => null,
            'confidence' => 0.60, 
            'needs_ai_fallback' => true, 
            'parsed_fields' => []
        ];

        // First evaluate if it is an emergency at all
        if (!self::isEmergency($cleanBody)) {
            return $result; 
        }

        $result['is_sos'] = true;
        $parsedFields = [];

        // --- Extra Extractors for Mobile App SOS Payload ---
        // 1. Victim Name
        if (preg_match('/(?:Victim|User|Citizen|Name):\s*([^\n\(\,]+)/i', $cleanBody, $m)) {
            $result['victim_name'] = trim($m[1]);
            $result['person_name'] = trim($m[1]);
            $parsedFields[] = 'victim_name';
        }

        // 2. Victim Phone
        if (preg_match('/(?:Tel|Phone):\s*([+0-9\s-]+)/i', $cleanBody, $m)) {
            $result['victim_phone'] = trim($m[1]);
            $parsedFields[] = 'victim_phone';
        }

        // 3. Blood Group
        if (preg_match('/(?:Blood|Blood Group):\s*([A-Za-z0-9+-]+)/i', $cleanBody, $m)) {
            $result['blood_group'] = strtoupper(trim($m[1]));
            $parsedFields[] = 'blood_group';
        }

        // 4. Age
        if (preg_match('/(?:Age):\s*(\d+)/i', $cleanBody, $m)) {
            $result['age'] = (int)$m[1];
            $parsedFields[] = 'age';
        }

        // 5. Medical Information / Conditions
        if (preg_match('/(?:Medical|Health Condition|Allergies|Medical Info):\s*([^\n]+)/i', $cleanBody, $m)) {
            $result['medical_info'] = trim($m[1]);
            $parsedFields[] = 'medical_info';
        }

        // 6. Emergency Contact
        if (preg_match('/(?:Emergency Contact|Next of Kin):\s*([^\n]+)/i', $cleanBody, $m)) {
            $result['emergency_contact'] = trim($m[1]);
            $parsedFields[] = 'emergency_contact';
        }

        // 7. Home Address
        if (preg_match('/(?:Home Address|Address):\s*([^\n]+)/i', $cleanBody, $m)) {
            $result['home_address'] = trim($m[1]);
            $parsedFields[] = 'home_address';
        }

        // 8. Google Maps URL
        if (preg_match('/(https?:\/\/[^\s]+maps[^\s]+)/i', $cleanBody, $m)) {
            $result['map_url'] = trim($m[1]);
            $parsedFields[] = 'map_url';
        }

        // --- Core Extract Coordinates (Any Path) ---
        $coords = self::extractCoordinates($cleanBody);
        if ($coords) {
            $result['latitude'] = $coords['latitude'];
            $result['longitude'] = $coords['longitude'];
            $result['location'] = $coords['latitude'] . ',' . $coords['longitude'];
            $parsedFields[] = 'latitude';
            $parsedFields[] = 'longitude';
            $parsedFields[] = 'location';
        }

        // --- PATH A: Structured Pipe Delimited Format ---
        // Expected: SOS|[DISASTER_TYPE]|[LOCATION]|[PEOPLE]|[INJURED]|[HELP_REQUIRED]|[PRIORITY]
        // Example: SOS|FLOOD|26.8467,80.9462|12|2|RESCUE|CRITICAL
        if (preg_match('/^(sos|help)\|/i', $cleanBody)) {
            $parts = explode('|', $cleanBody);
            if (count($parts) >= 3) {
                $result['disaster_type'] = !empty($parts[1]) ? strtolower(trim($parts[1])) : 'unknown';
                $parsedFields[] = 'disaster_type';

                // Check if structured location field contains coords
                $structLoc = !empty($parts[2]) ? trim($parts[2]) : null;
                $structCoords = self::extractCoordinates($structLoc);
                if ($structCoords) {
                    $result['latitude'] = $structCoords['latitude'];
                    $result['longitude'] = $structCoords['longitude'];
                    $result['location'] = $structCoords['latitude'] . ',' . $structCoords['longitude'];
                    $parsedFields[] = 'latitude';
                    $parsedFields[] = 'longitude';
                    $parsedFields[] = 'location';
                } elseif (!empty($structLoc)) {
                    $result['location'] = $structLoc;
                    $parsedFields[] = 'location';
                }
                
                if (isset($parts[3]) && is_numeric($parts[3])) {
                    $result['people_count'] = (int)$parts[3];
                    $parsedFields[] = 'people_count';
                }
                if (isset($parts[4]) && is_numeric($parts[4])) {
                    $result['injured_count'] = (int)$parts[4];
                    $parsedFields[] = 'injured_count';
                }
                
                $result['help_required'] = !empty($parts[5]) ? strtolower(trim($parts[5])) : null;
                $parsedFields[] = 'help_required';
                
                if (isset($parts[6])) {
                    $prio = strtoupper(trim($parts[6]));
                    if (in_array($prio, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])) {
                        $result['priority'] = $prio;
                        $parsedFields[] = 'priority';
                    }
                }
                
                $result['confidence'] = 1.00; 
                $result['needs_ai_fallback'] = false; 
                $result['parsed_fields'] = array_unique($parsedFields);
                
                self::simulateCoordinates($result);
                return $result;
            }
        }

        // --- PATH B: Heuristics Regex Engine (Best Effort) ---
        // 1. Identify Disaster Type
        $disasters = ['flood', 'fire', 'earthquake', 'accident', 'landslide', 'cyclone', 'medical'];
        foreach ($disasters as $disaster) {
            if (stripos($cleanBody, $disaster) !== false) {
                $result['disaster_type'] = $disaster;
                $parsedFields[] = 'disaster_type';
                break;
            }
        }

        // 2. Identify Location name (if coordinates not already found)
        if (!$coords) {
            $foundCity = null;
            $knownCities = ['rampur', 'lucknow', 'delhi', 'mumbai', 'chennai', 'kolkata', 'patna', 'guwahati', 'dehradun', 'bhuj', 'srinagar'];
            foreach ($knownCities as $city) {
                if (preg_match('/\b' . preg_quote($city, '/') . '\b/i', $cleanBody)) {
                    $foundCity = ucfirst($city);
                    break;
                }
            }

            if ($foundCity) {
                $result['location'] = $foundCity;
                $parsedFields[] = 'location';
                self::simulateCoordinates($result);
            } elseif (preg_match('/\b(at|in|near|near to|around|village|colony|area)\s+([A-Z][a-zA-Z\s]{2,15})\b/', $cleanBody, $matches)) {
                $result['location'] = trim($matches[2]);
                $parsedFields[] = 'location';
                self::simulateCoordinates($result);
            }
        }

        // 3. Identify People count
        if (preg_match('/(\d+)\s*(?:people|persons|trapped|stuck|lives|members)/i', $cleanBody, $matches)) {
            $result['people_count'] = (int)$matches[1];
            $parsedFields[] = 'people_count';
        }

        // 4. Identify Injured count
        if (preg_match('/(\d+)\s*(?:injured|hurt|wounded|casualties|hospitalized)/i', $cleanBody, $matches)) {
            $result['injured_count'] = (int)$matches[1];
            $parsedFields[] = 'injured_count';
        }

        // 5. Help Required
        $needs = ['medical', 'rescue', 'food', 'water', 'shelter', 'doctor', 'ambulance', 'fire brigade'];
        $foundNeeds = [];
        foreach ($needs as $need) {
            if (stripos($cleanBody, $need) !== false) {
                $foundNeeds[] = $need;
            }
        }
        if (!empty($foundNeeds)) {
            $result['help_required'] = implode(', ', $foundNeeds);
            $parsedFields[] = 'help_required';
        }

        // 6. Determine Priority
        if (stripos($cleanBody, 'critical') !== false || stripos($cleanBody, 'dying') !== false || stripos($cleanBody, 'immediate') !== false) {
            $result['priority'] = 'CRITICAL';
            $parsedFields[] = 'priority';
        } elseif (stripos($cleanBody, 'urgent') !== false || stripos($cleanBody, 'high') !== false || $result['injured_count'] > 0) {
            $result['priority'] = 'HIGH';
            $parsedFields[] = 'priority';
        }

        $result['parsed_fields'] = array_unique($parsedFields);

        // Check if we got enough rules-based details to skip AI fallback
        if (($coords || in_array('location', $parsedFields)) && in_array('people_count', $parsedFields)) {
            $result['needs_ai_fallback'] = false;
            $result['confidence'] = 0.85;
        }

        return $result;
    }

    /**
     * Regex Coordinates Parser & Validator
     * 
     * Extracts coordinates and validates bounds: -90 <= lat <= 90, -180 <= lng <= 180
     */
    public static function extractCoordinates($text) {
        if (empty($text)) return null;

        // Clean out labels to handle multiple syntaxes seamlessly
        $cleanText = str_ireplace(
            ['latitude:', 'longitude:', 'lat:', 'lng:', 'lon:', 'latitude=', 'longitude=', 'lat=', 'lng=', 'lon='], 
            ' ', 
            $text
        );
        
        // Find two decimal numbers separated by space, commas, or semicolons
        if (preg_match('/(-?\d+\.\d+)\s*[\s,;]+\s*(-?\d+\.\d+)/', $cleanText, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            
            if ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0) {
                return [
                    'latitude' => $lat,
                    'longitude' => $lng
                ];
            }
        }
        return null;
    }

    /**
     * Helper to mock coordinates in India for simulation mapping
     */
    private static function simulateCoordinates(&$result) {
        // Return if coordinates are already set
        if ($result['latitude'] !== null && $result['longitude'] !== null) {
            return;
        }
        if (empty($result['location'])) return;

        $loc = strtolower($result['location']);
        
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
            $result['latitude'] = $regions[$loc][0];
            $result['longitude'] = $regions[$loc][1];
        } else {
            // Generate standard random coord in Northern India corridor
            $result['latitude'] = 26.5 + (mt_rand(-1500, 1500) / 1000);
            $result['longitude'] = 79.5 + (mt_rand(-1500, 1500) / 1000);
        }
    }
}
?>
