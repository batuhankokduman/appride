<?php
// classes/FieldChangeDetector.php

class FieldChangeDetector {
    /**
     * İki dizi arasındaki değişen alanları bulur
     * @param array $old Önceki kayıt
     * @param array $new Yeni kayıt
     * @return array Değişen alanların isimleri
     */
    public static function getChangedFields(array $old, array $new): array {
        $changed = [];

        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old)) continue;

            // Diziler JSON olarak saklanıyor olabilir, o yüzden düzleştir
            $oldVal = is_array($old[$key]) ? json_encode($old[$key]) : (string) $old[$key];
            $newVal = is_array($value) ? json_encode($value) : (string) $value;

            if ($oldVal !== $newVal) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
    
public static function getNullToValueFields(array $oldData, array $newData): array
    {
        $changed = [];

        foreach ($newData as $key => $value) {
            if (!array_key_exists($key, $oldData)) continue;

            $old = $oldData[$key];

            if (is_null($old) || $old === '' || strtolower($old) === 'null') {
                if (!is_null($value) && $value !== '' && strtolower($value) !== 'null') {
                    $changed[] = $key;
                }
            }
        }

        return $changed;
    }

    
}
