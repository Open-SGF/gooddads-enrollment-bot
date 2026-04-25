<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ContactInfoDTO extends AbstractPdfDTO
{
    public function __construct(
        public string  $titleRegion           = '',
        public string  $fullName              = '',
        public string  $enteredDate           = '',
        public string  $address               = '',
        public ?string $employer              = null,
        public ?string $tshirtSize            = null,
        public ?string $phone                 = null,
        public ?string $workPhone             = null,
        public ?string $otherPhone            = null,
        public ?string $email                 = null,
        public ?string $caseworkerName        = null,
        public ?string $caseworkerPhone       = null,
        public ?string $monthlyChildSupport   = null,
        public ?string $maritalStatus         = null,
        public ?string $ethnicity             = null,
        public ?string $contactWithChildren   = null,
        public ?string $childrenCustody       = null,
        public ?string $childrenVisitation    = null,
        public ?string $childrenPhone         = null,
    ) {}

    protected function mandatoryFields(): array
    {
        return ['titleRegion', 
                'fullName', 
                'enteredDate', 
                'address', 
                'employer', 
                'tshirtSize', 
                'phone', 
                'workPhone', 
                'otherPhone', 
                'email', 
                'caseworkerName', 
                'caseworkerPhone',
                'contactWithChildren',
                'childrenCustody',
                'childrenVisitation',
                'childrenPhone',
                'monthlyChildSupport',
                'maritalStatus',
                'ethnicity'
                ];
    }

    public function toPdfArray(): array
    {
        return [
            'title_region'          => $this->titleRegion,
            'full_name'             => $this->fullName,
            'entered_date'          => $this->enteredDate,
            'address'               => $this->address,
            'employer'              => $this->employer,
            'tshirt_size'           => $this->tshirtSize,
            'phone'                 => $this->phone,
            'work_phone'            => $this->workPhone,
            'other_phone'           => $this->otherPhone,
            'email'                 => $this->email,
            'case_worker_name'      => $this->caseworkerName,
            'case_worker_phone'     => $this->caseworkerPhone,
            'monthly_child_support' => $this->monthlyChildSupport,
            'marital_status'        => $this->maritalStatus,
            'ethnicity'             => $this->ethnicity,
            'contact_with_children' => $this->contactWithChildren,
            'children_custody'      => $this->childrenCustody,
            'children_visitation'   => $this->childrenVisitation,
            'children_phone'        => $this->childrenPhone,
        ];
    }
}

?>