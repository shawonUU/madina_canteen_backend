use Illuminate\Support\Facades\DB;
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Gate Pass - {{ $gatePass->pass_no }}</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 18px 28px 20px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            font-size: 8px;
        }

        .page {
            width: 100%;
        }

        /* =========================
           HEADER
        ========================= */

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .logo-left {
            width: 18%;
            text-align: center;
        }

        .logo-middle {
            width: 28%;
            text-align: center;
        }

        .company-info {
            width: 54%;
            text-align: left;
            padding-left: 5px !important;
        }

        .logo-left img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .logo-middle img {
            width: 72px;
            height: auto;
            object-fit: contain;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            line-height: 11px;
        }

        .company-title {
            font-size: 10px;
            font-weight: bold;
            line-height: 10px;
        }

        .company-address {
            font-size: 7px;
            line-height: 9px;
        }

        .company-contact {
            font-size: 7px;
            line-height: 9px;
        }

        .document-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-top: 2px;
            margin-bottom: 7px;
        }

        /* =========================
           BASIC INFO
        ========================= */

        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .info-table td {
            border: 1px solid #000;
            height: 17px;
            padding: 2px 4px;
            font-size: 7.5px;
            vertical-align: middle;
        }

        .label {
            font-weight: bold;
            width: 18%;
        }

        .value {
            width: 32%;
        }

        /* =========================
           MATERIAL TABLE
        ========================= */

        .section-title {
            font-size: 8px;
            font-weight: bold;
            margin-top: 7px;
            margin-bottom: 2px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 2px 3px;
            font-size: 7px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .items-table th {
            font-weight: bold;
            text-align: center;
            height: 25px;
        }

        .items-table td {
            height: 20px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* =========================
           REMARKS
        ========================= */

        .remarks-label {
            border: 1px solid #000;
            border-top: 0;
            font-size: 7.5px;
            font-weight: bold;
            padding: 3px 4px;
            margin-top: 0;
        }

        .remarks-box {
            border: 1px solid #000;
            min-height: 95px;
            padding: 4px;
            font-size: 7.5px;
        }

        /* =========================
           PURPOSE
        ========================= */

        .purpose-box {
            border: 1px solid #000;
            min-height: 32px;
            padding: 4px;
            font-size: 7.5px;
        }

        /* =========================
           SIGNATURE AREA
        ========================= */

        .signature-note {
            font-size: 7.5px;
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 0;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signature-table td {
            width: 50%;
            border: 1px solid #000;
            height: 92px;
            padding: 3px;
            text-align: center;
            vertical-align: top;
        }

        .signature-title {
            font-size: 7.5px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .signature-image {
            width: 62px;
            height: 35px;
            object-fit: contain;
            margin: 1px auto 0 auto;
        }

        .signature-name {
            font-size: 6.5px;
            line-height: 8px;
            margin-top: 0;
        }

        .signature-designation {
            font-size: 6.5px;
            line-height: 8px;
        }

        .signature-date {
            font-size: 6px;
            line-height: 7px;
            margin-top: 2px;
        }

        /* =========================
           STATUS
        ========================= */

        .status {
            font-weight: bold;
        }

        /* =========================
           PRINT
        ========================= */

        .no-border {
            border: 0 !important;
        }
    </style>
</head>

<body>
<div class="page">

    <table class="header-table" style="margin-top:15px;">
        <tr>
            <td class="logo-left">
                <img
                    src="{{ public_path('images/madina_logo.png') }}"
                    alt="Madina Group"
                >
            </td>

            <td class="logo-middle">
                <img
                    src="{{ public_path('images/madina_maritime_logo.png') }}"
                    alt="Madina Maritime"
                >
            </td>

            <td class="company-info">
                <div class="company-name">Madina Group</div>
                <div class="company-title">Madina Maritime Limited</div>

                <div class="company-address">
                    Head Office: Madina Square (3rd Floor), 66/A Shahid Badrul Miah Chowdhury
                </div>

                <div class="company-address">
                    Sharif (Central Road) Dhanmondi, Dhaka-1205, Bangladesh
                </div>

                <div class="company-contact">
                    www.madina.co, Phone: +0222336358, +022336358
                </div>
            </td>
        </tr>
    </table>

    <div class="document-title" style="margin-top: 50px; margin-bottom: 5px;">
        GATE PASS
    </div>

    {{-- =========================================================
         GATE PASS INFORMATION
    ========================================================== --}}
    <table class="info-table">

        <tr>
            <td class="label">Pass No</td>
            <td class="value">
                {{ $gatePass->pass_no }}
            </td>

            <td class="label">Department</td>
            <td class="value">
                {{ $gatePass->department_name ?? '--' }}
            </td>
        </tr>

        <tr>
            <td class="label">Requested By</td>
            <td class="value">
                {{ $gatePass->requester?->name ?? '--' }}
            </td>

            <td class="label">Gate Pass Type</td>
            <td class="value">
                {{ ucfirst(str_replace('_', ' ', $gatePass->gate_pass_type)) }}
            </td>
        </tr>

        <tr>
            <td class="label">Designation</td>
            <td class="value">
                {{ $gatePass->designation_name ?? '--' }}
            </td>

            <td class="label">Status</td>
            <td class="value status">
                {{ ucfirst($gatePass->status ?? '--') }}
            </td>
        </tr>

        <tr>
            <td class="label">Expected Exit</td>
            <td class="value">
                {{ $gatePass->expected_exit_at ?? '--' }}
            </td>

            <td class="label">Expected Return</td>
            <td class="value">
                {{ $gatePass->expected_return_at ?? '--' }}
            </td>
        </tr>

        <tr>
            <td class="label">Purpose</td>
            <td colspan="3">
                {{ $gatePass->purpose ?? '--' }}
            </td>
        </tr>

    </table>

    {{-- =========================================================
         MATERIAL DETAILS
    ========================================================== --}}
    @if(in_array($gatePass->gate_pass_type, ['Person With Material']))

        <div class="section-title">
            Material Details
        </div>

        @if($gatePass->items && $gatePass->items->count())

            <table class="items-table">

                <thead>
                    <tr>
                        <th style="width: 5%;">SL</th>
                        <th style="width: 25%;">Product / Material</th>
                        <th style="width: 9%;">Qty</th>
                        <th style="width: 9%;">Unit</th>
                        <th style="width: 15%;">Asset No</th>
                        <th style="width: 15%;">Serial No</th>
                        <th style="width: 22%;">Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($gatePass->items as $index => $item)
                        <tr>
                            <td class="text-center">
                                {{ $index + 1 }}
                            </td>

                            <td>
                                {{ $item->product_name }}
                            </td>

                            <td class="text-center">
                                {{ $item->quantity }}
                            </td>

                            <td class="text-center">
                                {{ $item->unit ?? '--' }}
                            </td>

                            <td>
                                {{ $item->asset_no ?? '--' }}
                            </td>

                            <td>
                                {{ $item->serial_no ?? '--' }}
                            </td>

                            <td>
                                {{ $item->remarks ?? '--' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>

        @else

            <div class="remarks-box" style="min-height: 35px;">
                No material items.
            </div>

        @endif

    @endif

    {{-- =========================================================
         REMARKS
    ========================================================== --}}
    <div class="remarks-label">
        Remarks: {{ $gatePass->remarks ?? '' }}
    </div>

    {{-- =========================================================
         SIGNATURE SECTION
    ========================================================== --}}
    <div class="signature-note">
        - For Gate Pass Department
    </div>

<table class="signature-table">

    <tr>

        {{-- Requested By --}}
        <td>
            <div class="signature-title">
                Requested By
            </div>

            <img
                class="signature-image"
                src="{{ public_path('images/signatures/requested_by.png') }}"
                alt="Signature"
            >

            <div class="signature-name">
                {{ $gatePass->requester?->name ?? '--' }}
            </div>

            <div class="signature-designation">
                {{ $gatePass->designation_name ?? '--' }}
            </div>

            <div class="signature-date">
                Date:
                {{ optional($gatePass->created_at)->format('d-m-Y h:i A') ?? '--' }}
            </div>
        </td>


        {{-- Approved By --}}
        @php
            $approvedLevel = $gatePass?->approvalRequest?->levels;
        @endphp

        @foreach ( $approvedLevel as $level )
            <td>
                <div class="signature-title">
                    Approved By
                </div>

                <img
                    class="signature-image"
                    src="{{ public_path('images/signatures/authorized_by.png') }}"
                    alt="Signature"
                >

                <div class="signature-name">
                    {{ $level?->approver?->name ?? '--' }}
                </div>

                <div class="signature-designation">
                    {{ $level ? 'Level ' . $level->level_no : '--' }}
                </div>

                <div class="signature-date">
                    Date:
                    {{ optional($level?->action_at)->format('d-m-Y h:i A') ?? '--' }}
                </div>
            </td>
        @endforeach

    </tr>

</table>

</div>
</body>
</html>