<?php

namespace Src\Controller;

class ApplicantEvaluator
{
    private const CORE_SUBJECTS = [
        'english language' => ['english language', 'english lang', 'english'],
        'core mathematics' => ['core mathematics', 'core maths'],
        'integrated science' => ['integrated science', 'int science']
    ];

    private const ELECTIVE_SUBJECTS = [
        'science_technical' => [
            'elective mathematics' => ['elective mathematics', 'elective maths'],
            'physics',
            'chemistry',
            'biology',
            'technical drawing',
            'ict'
        ],
        'general' => [
            'elective mathematics' => ['elective mathematics', 'elective maths'],
            'geography',
            'economics',
            'business',
            'visual arts',
            'home economics',
            'agricultural science'
        ]
    ];

    private const PROGRAM_REQUIREMENTS = [
        'degree' => [
            'A' => [
                'core_subjects' => 3,
                'required_electives' => ['elective mathematics'],
                'additional_electives' => 2,
                'elective_type' => 'science_technical',
                'min_grade' => 'C6',
                'alternate_path' => [
                    'required_electives' => ['elective mathematics', 'geography'],
                    'additional_electives' => 1,
                    'elective_type' => 'general'
                ]
            ],
            'B' => [
                'core_subjects' => 3,
                'additional_electives' => 3,
                'elective_type' => 'general',
                'min_grade' => 'C6'
            ]
        ],
        'diploma' => [
            'all' => [
                'core_subjects' => 2, // English and Mathematics
                'additional_cores' => 1,
                'additional_electives' => 3,
                'elective_type' => 'general',
                'min_grade' => 'D7'
            ]
        ]
    ];

    private $gradeRanges;

    public function __construct(array $gradeRanges)
    {
        $this->gradeRanges = $gradeRanges;
    }

    public function evaluateApplicant(array $applicant, string $programType = 'degree'): array
    {
        $programGroup = $applicant['prog_info']['group'];
        $programCategory = strtolower($programType);

        $requirements = self::PROGRAM_REQUIREMENTS[$programCategory][$programGroup] ??
            self::PROGRAM_REQUIREMENTS[$programCategory]['all'];

        $results = $this->processResults($applicant['sch_rslt']);

        // Check if applicant meets nautical science alternate path
        $isNauticalScienceAlternate = $this->checkNauticalScienceAlternatePath(
            $programGroup,
            $results,
            $requirements['alternate_path'] ?? null
        );

        $evaluation = $this->evaluateSubjects(
            $results,
            $requirements,
            $isNauticalScienceAlternate
        );

        return [
            'program_group' => $programGroup,
            'program_type' => $programCategory,
            'qualified' => $this->isQualified($evaluation, $requirements),
            'scores' => $evaluation['scores'],
            'subjects_passed' => $evaluation['subjects_passed'],
            'required_core_passed' => count($evaluation['subjects_passed']['core']),
            'required_elective_passed' => count($evaluation['subjects_passed']['required_electives']),
            'required_core_subjects' => $evaluation['subjects_passed']['core'],
            'required_elective_subjects' => $evaluation['subjects_passed']['required_electives'],
            'any_elective_subjects' => $evaluation['subjects_passed']['additional_electives'],
            'total_core_score' => $evaluation['scores']['core'],
            'total_elective_score' => $evaluation['scores']['elective'],
            'total_score' => $evaluation['scores']['total']
        ];
    }

    private function processResults(array $results): array
    {
        $processedResults = [
            'core' => [],
            'elective' => [],
            'scores' => []
        ];

        foreach ($results as $result) {
            $subject = strtolower($result['subject']);
            $type = strtolower($result['type']);
            $score = $this->calculateScore($result['grade']);

            if ($score !== null) {
                $processedResults[$type][$subject] = [
                    'grade' => $result['grade'],
                    'score' => $score
                ];
                $processedResults['scores'][$subject] = $score;
            }
        }

        return $processedResults;
    }

    private function calculateScore(string $grade): ?int
    {
        foreach ($this->gradeRanges as $range) {
            if ($grade === $range['grade']) {
                return $range['score'];
            }
        }
        return null;
    }

    private function evaluateSubjects(
        array $results,
        array $requirements,
        bool $isAlternatePath
    ): array {
        $evaluation = [
            'scores' => [
                'core' => 0,
                'elective' => 0,
                'total' => 0
            ],
            'subjects_passed' => [
                'core' => [],
                'required_electives' => [],
                'additional_electives' => []
            ]
        ];

        // Evaluate core subjects
        foreach (self::CORE_SUBJECTS as $subject => $aliases) {
            $this->evaluateCoreSubject($subject, $aliases, $results, $requirements, $evaluation);
        }

        // Evaluate electives based on path
        $electiveRequirements = $isAlternatePath ?
            $requirements['alternate_path'] :
            $requirements;

        $this->evaluateElectives($results, $electiveRequirements, $evaluation);

        $evaluation['scores']['total'] = $evaluation['scores']['core'] +
            $evaluation['scores']['elective'];

        return $evaluation;
    }

    private function evaluateCoreSubject(
        string $subject,
        array $aliases,
        array $results,
        array $requirements,
        array &$evaluation
    ): void {
        foreach ($aliases as $alias) {
            if (isset($results['core'][$alias])) {
                $score = $results['core'][$alias]['score'];
                $grade = $results['core'][$alias]['grade'];

                if ($this->isPassingGrade($grade, $requirements['min_grade'])) {
                    $evaluation['scores']['core'] += $score;
                    $evaluation['subjects_passed']['core'][] = $subject;
                    break;
                }
            }
        }
    }

    private function evaluateElectives(array $results, array $requirements, array &$evaluation): void
    {
        // First, evaluate required electives
        if (isset($requirements['required_electives'])) {
            foreach ($requirements['required_electives'] as $subject) {
                $this->evaluateRequiredElective($subject, $results, $requirements, $evaluation);
            }
        }

        // Then, evaluate additional electives
        $this->evaluateAdditionalElectives(
            $results,
            $requirements,
            $evaluation,
            $requirements['additional_electives']
        );
    }

    private function evaluateRequiredElective(string $subject, array $results, array $requirements, array &$evaluation): void
    {
        $aliases = self::ELECTIVE_SUBJECTS['science_technical'][$subject] ?? [$subject];

        foreach ($aliases as $alias) {
            if (isset($results['elective'][$alias])) {
                $score = $results['elective'][$alias]['score'];
                $grade = $results['elective'][$alias]['grade'];

                if ($this->isPassingGrade($grade, $requirements['min_grade'])) {
                    $evaluation['scores']['elective'] += $score;
                    $evaluation['subjects_passed']['required_electives'][] = $subject;
                    break;
                }
            }
        }
    }

    private function evaluateAdditionalElectives(array $results, array $requirements, array &$evaluation, int $requiredCount): void
    {
        $validElectives = [];
        $electiveType = $requirements['elective_type'];

        foreach ($results['elective'] as $subject => $result) {
            if (
                $this->isValidElectiveSubject($subject, $electiveType) &&
                $this->isPassingGrade($result['grade'], $requirements['min_grade'])
            ) {
                $validElectives[$subject] = $result['score'];
            }
        }

        asort($validElectives);
        $selectedElectives = array_slice($validElectives, 0, $requiredCount, true);

        foreach ($selectedElectives as $subject => $score) {
            $evaluation['scores']['elective'] += $score;
            $evaluation['subjects_passed']['additional_electives'][] = $subject;
        }
    }

    private function isValidElectiveSubject(string $subject, string $type): bool
    {
        return in_array($subject, self::ELECTIVE_SUBJECTS[$type]) || isset(self::ELECTIVE_SUBJECTS[$type][$subject]);
    }

    private function isPassingGrade(string $grade, string $minGrade): bool
    {
        $gradeValues = [
            'A1' => 1,
            'B2' => 2,
            'B3' => 3,
            'C4' => 4,
            'C5' => 5,
            'C6' => 6,
            'D7' => 7,
            'E8' => 8,
            'F9' => 9
        ];

        return $gradeValues[$grade] <= $gradeValues[$minGrade];
    }

    private function checkNauticalScienceAlternatePath(string $programGroup, array $results, ?array $alternatePath): bool
    {
        if ($programGroup !== 'B' || !$alternatePath) {
            return false;
        }

        $hasElectiveMaths = false;
        $hasGeography = false;
        $hasThirdElective = false;

        foreach ($results['elective'] as $subject => $result) {
            if (in_array($subject, ['elective mathematics', 'elective maths'])) {
                $hasElectiveMaths = true;
            } elseif ($subject === 'geography') {
                $hasGeography = true;
            } else {
                $hasThirdElective = true;
            }
        }

        return $hasElectiveMaths && $hasGeography && $hasThirdElective;
    }

    private function isQualified(array $evaluation, array $requirements): bool
    {
        $corePassed = count($evaluation['subjects_passed']['core']) >= $requirements['core_subjects'];

        $requiredElectivesPassed = !isset($requirements['required_electives']) ||
            count($evaluation['subjects_passed']['required_electives']) >=
            count($requirements['required_electives']);

        $additionalElectivesPassed = count($evaluation['subjects_passed']['additional_electives']) >=
            $requirements['additional_electives'];

        return $corePassed && $requiredElectivesPassed && $additionalElectivesPassed;
    }
}
