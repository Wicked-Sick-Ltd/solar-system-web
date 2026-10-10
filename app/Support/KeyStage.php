<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * England Key Stage → usual US grade band. One map for labels, filters and schema.
 *
 * UK ages and year groups, from GOV.UK:
 * - Early Years Foundation Stage: the national curriculum overview lists ages
 *   3–5 (nursery, then Reception). The statutory framework covers birth to 5;
 *   this label uses the 3–5 school band.
 *   https://www.gov.uk/national-curriculum
 * - Key Stage 1: ages 5–7, Years 1–2.
 * - Key Stage 2: ages 7–11, Years 3–6.
 * - Key Stage 3: ages 11–14, Years 7–9.
 * - Key Stage 4: ages 14–16, Years 10–11.
 *   https://www.gov.uk/government/publications/national-curriculum-in-england-framework-for-key-stages-1-to-4/the-national-curriculum-in-england-framework-for-key-stages-1-to-4
 * - Key Stage 5: ages 16–18, Years 12–13 (sixth form). It sits outside the
 *   statutory KS1–KS4 framework; 16–19 study programmes are described at
 *   https://www.gov.uk/guidance/16-to-19-funding-how-it-works
 *
 * US grades follow the usual age match (Kindergarten about ages 5–6, Grade 12
 * about ages 17–18):
 * - EYFS ≈ Pre-K–Kindergarten. Kindergarten also overlaps Year 1, because the
 *   UK 1 September cutoff and US state cutoffs differ by weeks.
 * - KS1 ≈ Kindergarten–Grade 1 (ages 5–7).
 * - KS2 ≈ Grades 2–5 (ages 7–11).
 * - KS3 ≈ Grades 6–8 (ages 11–14).
 * - KS4 ≈ Grades 9–10 (ages 14–16).
 * - KS5 ≈ Grades 11–12 (ages 16–18).
 *
 * Checked against:
 * - The Good Schools Guide, “Comparing US and UK Grades & Exams”,
 *   https://www.goodschoolsguide.co.uk/international/advice/us-vs-uk-exam-comparison
 *   (Year 1 ≈ Kindergarten, Year 13 ≈ Grade 12, Key Stage 5 ≈ Grades 11–12).
 * - School Atlas, “Moving to the UK from the USA”,
 *   https://www.schoolatlas.co.uk/guides/moving-to-uk-from-usa
 *   (Grade 1 ≈ Year 2 / KS1, Grade 5 ≈ Year 6 / KS2, Grade 12 ≈ Year 13 / KS5).
 *
 * These are classroom equivalences, not a school placement decision.
 */
final class KeyStage
{
    /**
     * Grade numbers: -1 is Pre-K, 0 is Kindergarten, 1–12 are US grades.
     *
     * @var array<string, array{name: string, age_min: int, age_max: int, grade_min: int, grade_max: int}>
     */
    private const STAGES = [
        'EYFS' => ['name' => 'Early Years Foundation Stage', 'age_min' => 3, 'age_max' => 5, 'grade_min' => -1, 'grade_max' => 0],
        'KS1' => ['name' => 'Key Stage 1', 'age_min' => 5, 'age_max' => 7, 'grade_min' => 0, 'grade_max' => 1],
        'KS2' => ['name' => 'Key Stage 2', 'age_min' => 7, 'age_max' => 11, 'grade_min' => 2, 'grade_max' => 5],
        'KS3' => ['name' => 'Key Stage 3', 'age_min' => 11, 'age_max' => 14, 'grade_min' => 6, 'grade_max' => 8],
        'KS4' => ['name' => 'Key Stage 4', 'age_min' => 14, 'age_max' => 16, 'grade_min' => 9, 'grade_max' => 10],
        'KS5' => ['name' => 'Key Stage 5', 'age_min' => 16, 'age_max' => 18, 'grade_min' => 11, 'grade_max' => 12],
    ];

    /** @var list<string> */
    public const ORDER = ['EYFS', 'KS1', 'KS2', 'KS3', 'KS4', 'KS5'];

    /** Compact label, e.g. "KS2 · US Grades 2–5 · Ages 7–11". */
    public static function compact(string $code): string
    {
        return self::span([$code]);
    }

    /**
     * One label for a contiguous run, or a comma-separated list otherwise.
     *
     * @param  list<string>  $codes
     */
    public static function span(array $codes): string
    {
        $codes = self::normalize($codes);

        if (! self::isContiguous($codes)) {
            return implode(', ', array_map(static fn (string $code): string => self::compact($code), $codes));
        }

        $first = self::STAGES[$codes[0]];
        $last = self::STAGES[$codes[array_key_last($codes)]];
        $codeLabel = count($codes) === 1 ? $codes[0] : $codes[0].'–'.$codes[array_key_last($codes)];

        return $codeLabel.' · '.self::usPhrase($first['grade_min'], $last['grade_max'], false)
            .' · Ages '.$first['age_min'].'–'.$last['age_max'];
    }

    /**
     * Spoken form for screen readers. Abbreviations are expanded.
     *
     * @param  list<string>  $codes
     */
    public static function accessible(array $codes): string
    {
        $codes = self::normalize($codes);

        if (! self::isContiguous($codes)) {
            return implode('; ', array_map(static fn (string $code): string => self::accessible([$code]), $codes));
        }

        $first = self::STAGES[$codes[0]];
        $last = self::STAGES[$codes[array_key_last($codes)]];
        $name = count($codes) === 1 ? $first['name'] : $first['name'].' to '.$last['name'];

        return $name.', '.self::usPhrase($first['grade_min'], $last['grade_max'], true)
            .', ages '.$first['age_min'].' to '.$last['age_max'];
    }

    /**
     * schema.org educationalLevel values: the UK name, the compact label, and
     * the collapsed span when a resource covers more than one stage.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function educationalLevels(array $codes): array
    {
        $codes = self::normalize($codes);
        $levels = [];

        foreach ($codes as $code) {
            $levels[] = self::STAGES[$code]['name'];
            $levels[] = self::compact($code);
        }

        if (count($codes) > 1) {
            $levels[] = self::span($codes);
        }

        return array_values(array_unique($levels));
    }

    /**
     * schema.org typicalAgeRange. Uses an ASCII hyphen, as in the spec examples.
     *
     * @param  list<string>  $codes
     */
    public static function typicalAgeRange(array $codes): string
    {
        $codes = self::normalize($codes);

        if (! self::isContiguous($codes)) {
            return implode(', ', array_map(
                static fn (string $code): string => self::STAGES[$code]['age_min'].'-'.self::STAGES[$code]['age_max'],
                $codes,
            ));
        }

        $first = self::STAGES[$codes[0]];
        $last = self::STAGES[$codes[array_key_last($codes)]];

        return $first['age_min'].'-'.$last['age_max'];
    }

    /**
     * Replace :eyfs, :ks1 … :ks5 in authored copy with the compact label.
     */
    public static function interpolate(string $template): string
    {
        $replace = [];

        foreach (self::ORDER as $code) {
            $replace[':'.strtolower($code)] = self::compact($code);
        }

        return strtr($template, $replace);
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function normalize(array $codes): array
    {
        $unique = [];

        foreach ($codes as $code) {
            $code = strtoupper(trim($code));

            if ($code === '') {
                continue;
            }

            if (! isset(self::STAGES[$code])) {
                throw new InvalidArgumentException("Unknown Key Stage [{$code}].");
            }

            $unique[$code] = true;
        }

        if ($unique === []) {
            throw new InvalidArgumentException('Choose at least one Key Stage.');
        }

        $ordered = [];

        foreach (self::ORDER as $code) {
            if (isset($unique[$code])) {
                $ordered[] = $code;
            }
        }

        return $ordered;
    }

    /** @param  list<string>  $codes */
    private static function isContiguous(array $codes): bool
    {
        if (count($codes) <= 1) {
            return true;
        }

        $indexes = array_flip(self::ORDER);

        for ($i = 1, $n = count($codes); $i < $n; $i++) {
            if ($indexes[$codes[$i]] !== $indexes[$codes[$i - 1]] + 1) {
                return false;
            }
        }

        return true;
    }

    private static function usPhrase(int $min, int $max, bool $accessible): string
    {
        if (! $accessible) {
            $from = self::gradeToken($min);
            $to = self::gradeToken($max);
            $range = $from === $to ? $from : $from.'–'.$to;

            return ($min < 0 && $max <= 0) ? 'US '.$range : 'US Grades '.$range;
        }

        if ($min > 0 && $max > 0) {
            return $min === $max ? 'US Grade '.$min : 'US Grades '.$min.' to '.$max;
        }

        $from = self::gradeWords($min);
        $to = self::gradeWords($max);

        return $from === $to ? 'US '.$from : 'US '.$from.' to '.$to;
    }

    private static function gradeToken(int $grade): string
    {
        return match (true) {
            $grade < 0 => 'Pre-K',
            $grade === 0 => 'K',
            default => (string) $grade,
        };
    }

    private static function gradeWords(int $grade): string
    {
        return match (true) {
            $grade < 0 => 'Pre-Kindergarten',
            $grade === 0 => 'Kindergarten',
            default => 'Grade '.$grade,
        };
    }
}
