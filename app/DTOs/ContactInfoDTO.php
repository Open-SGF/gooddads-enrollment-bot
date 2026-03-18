<?php

namespace App\DTOs;

readonly class ContactInfoDTO implements PdfArrayable
{
    public function __construct(
        public readonly string $titleRegion,
        public readonly string $fullName,
        public readonly string $enteredDate,
        public readonly string $address,
        public readonly string $employer,
        public readonly string $tshirtSize,                   
        public readonly string $phone,
        public readonly string $workPhone,
        public readonly string $otherPhone,
        public readonly string $email,
        public readonly string $caseworkerName,
        public readonly string $caseworkerPhone,
        public readonly string $monthlyChildSupport,
        public readonly string $maritalStatus,
        public readonly string $ethnicity,
        public readonly string $contactWithChildren,
        public readonly string $childrenCustody,
        public readonly string $childrenVisitation,
        public readonly string $childrenPhone,
    ) {}

    public function toPdfArray(): array
    {
        return [
            'title_region'                                      => $this->titleRegion,
            'full_name'                                         => $this->fullName,
            'entered_date'                                      => $this->enteredDate,
            'address'                                           => $this->address,
            'employer'                                          => $this->employer,
            'tshirt_size'                                       => $this->tshirtSize,
            'phone'                                             => $this->phone,
            'work_phone'                                        => $this->workPhone,
            'other_phone'                                       => $this->otherPhone,
            'email'                                             => $this->email,
            'case_worker_name'                                  => $this->caseworkerName,
            'case_worker_phone'                                 => $this->caseworkerPhone,
            'monthly_child_support'                             => $this->monthlyChildSupport,
            'marital_status'                                    => $this->maritalStatus,
            'ethnicity'                                         => $this->ethnicity,
            'contact_with_children'                             => $this->contactWithChildren,
            'children_custody'                                  => $this->childrenCustody,
            'children_visitation'                               => $this->childrenVisitation,
            'children_phone'                                    => $this->childrenPhone,
        ];
    }
}
?>