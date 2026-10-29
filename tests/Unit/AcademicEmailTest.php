<?php

declare(strict_types=1);

use App\Support\AcademicEmail;

it('flags academic domains for the institution tier', function (string $email): void {
    expect(AcademicEmail::isEligible($email))->toBeTrue();
})->with([
    'teacher@cam.ac.uk',
    'student@ox.ac.uk',
    'person@dept.cam.ac.uk',
    'researcher@mit.edu',
    'Person@MIT.EDU',
    'a@cs.stanford.edu',
    'a@sydney.edu.au',
    'a@uni.edu.cn',
    'a@u-tokyo.ac.jp',
    'a@uct.ac.za',
    'a@university.ac.nz',
]);

it('does not flag ordinary or lookalike domains', function (string $email): void {
    expect(AcademicEmail::isEligible($email))->toBeFalse();
})->with([
    'person@gmail.com',
    'person@university.ac.com',
    'person@notedu.com',
    'person@evil.edu.example.com',
    'person@mail.uni-muenchen.de',
    'not-an-email',
    '@mit.edu',
    '',
]);
