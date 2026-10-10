<?php

declare(strict_types=1);

use App\Support\KeyStage;

it('maps each England Key Stage to the usual US grades and ages', function () {
    expect(KeyStage::compact('EYFS'))->toBe('EYFS · US Pre-K–K · Ages 3–5')
        ->and(KeyStage::compact('KS1'))->toBe('KS1 · US Grades K–1 · Ages 5–7')
        ->and(KeyStage::compact('KS2'))->toBe('KS2 · US Grades 2–5 · Ages 7–11')
        ->and(KeyStage::compact('KS3'))->toBe('KS3 · US Grades 6–8 · Ages 11–14')
        ->and(KeyStage::compact('KS4'))->toBe('KS4 · US Grades 9–10 · Ages 14–16')
        ->and(KeyStage::compact('KS5'))->toBe('KS5 · US Grades 11–12 · Ages 16–18');
});

it('collapses a contiguous run and keeps a gap as separate labels', function () {
    expect(KeyStage::span(['KS2', 'KS1']))->toBe('KS1–KS2 · US Grades K–5 · Ages 5–11')
        ->and(KeyStage::span(['KS5', 'KS3', 'KS4']))->toBe('KS3–KS5 · US Grades 6–12 · Ages 11–18')
        ->and(KeyStage::span(['KS1', 'KS3']))->toBe('KS1 · US Grades K–1 · Ages 5–7, KS3 · US Grades 6–8 · Ages 11–14');
});

it('expands abbreviations for screen readers', function () {
    expect(KeyStage::accessible(['KS2']))->toBe('Key Stage 2, US Grades 2 to 5, ages 7 to 11')
        ->and(KeyStage::accessible(['ks1']))->toBe('Key Stage 1, US Kindergarten to Grade 1, ages 5 to 7')
        ->and(KeyStage::accessible(['EYFS']))->toBe('Early Years Foundation Stage, US Pre-Kindergarten to Kindergarten, ages 3 to 5')
        ->and(KeyStage::accessible(['KS1', 'KS2']))->toBe('Key Stage 1 to Key Stage 2, US Kindergarten to Grade 5, ages 5 to 11')
        ->and(KeyStage::accessible(['KS3', 'KS4', 'KS5']))->toBe('Key Stage 3 to Key Stage 5, US Grades 6 to 12, ages 11 to 18');
});

it('builds schema.org level and age values from the same map', function () {
    expect(KeyStage::educationalLevels(['KS1', 'KS2']))->toBe([
        'Key Stage 1',
        'KS1 · US Grades K–1 · Ages 5–7',
        'Key Stage 2',
        'KS2 · US Grades 2–5 · Ages 7–11',
        'KS1–KS2 · US Grades K–5 · Ages 5–11',
    ])->and(KeyStage::typicalAgeRange(['KS1', 'KS2']))->toBe('5-11')
        ->and(KeyStage::typicalAgeRange(['KS3', 'KS4', 'KS5']))->toBe('11-18')
        ->and(KeyStage::typicalAgeRange(['KS1', 'KS3']))->toBe('5-7, 11-14');
});

it('fills authored copy from the compact label', function () {
    expect(KeyStage::interpolate('Activities for :ks1 and :ks2.'))
        ->toBe('Activities for KS1 · US Grades K–1 · Ages 5–7 and KS2 · US Grades 2–5 · Ages 7–11.');
});

it('rejects an unknown or empty stage', function () {
    expect(fn () => KeyStage::compact('KS9'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => KeyStage::span([]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => KeyStage::span(['Key Stage 2']))->toThrow(InvalidArgumentException::class);
});
