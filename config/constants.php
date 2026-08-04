<?php

declare(strict_types=1);

/** Application roles (RBAC) */
const ROLE_REGISTRAR = 'registrar';
const ROLE_ENCODER = 'encoder';

/** Student lifecycle statuses */
const STUDENT_STATUS_ACTIVE = 'active';
const STUDENT_STATUS_TRANSFERRED = 'transferred';
const STUDENT_STATUS_GRADUATED = 'graduated';
const STUDENT_STATUS_DROPPED = 'dropped';

const STUDENT_STATUSES = [
    STUDENT_STATUS_ACTIVE,
    STUDENT_STATUS_TRANSFERRED,
    STUDENT_STATUS_GRADUATED,
    STUDENT_STATUS_DROPPED,
];

/** Admission workflow statuses */
const ADMISSION_STATUS_PENDING = 'pending';
const ADMISSION_STATUS_APPROVED = 'approved';
const ADMISSION_STATUS_REJECTED = 'rejected';

/** Enrollment types */
const ENROLLMENT_TYPES = ['new', 'returning', 'transferee'];

/** Sex values accepted by forms / DB enums */
const SEX_OPTIONS = ['Male', 'Female'];

/** Junior high grade levels (display names seeded in schema) */
const GRADE_LEVEL_NAMES = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];

/** School year label pattern: YYYY-YYYY */
const SCHOOL_YEAR_PATTERN = '/^\d{4}-\d{4}$/';
