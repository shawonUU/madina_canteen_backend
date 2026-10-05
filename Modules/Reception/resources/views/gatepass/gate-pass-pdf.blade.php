<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Gate Pass - {{ $gatePass->pass_no }}
    </title>

    <style>
        @page {
            margin: 25px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 4px;
        }

        .document-title {
            font-size: 17px;
            font-weight: bold;
            margin-top: 6px;
        }

        .document-subtitle {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .info-table td {
            border: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
        }

        .label {
            width: 18%;
            background: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }

        .value {
            width: 32%;
        }

        .section-title {
            color: #000;
            font-size: 12px;
            font-weight: bold;
            padding: 8px 10px;
            margin-top: 15px;
            margin-bottom: 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .items-table th {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 7px;
            font-size: 10px;
            text-align: left;
        }

        .items-table td {
            border: 1px solid #d1d5db;
            padding: 7px;
            font-size: 10px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .remarks-box {
            border: 1px solid #ddd;
            min-height: 55px;
            padding: 9px;
            margin-bottom: 20px;
        }

        .status {
            display: inline-block;
            padding: 4px 9px;
            border: 1px solid #ddd;
            font-weight: bold;
            text-transform: capitalize;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 55px;
        }

        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 30px;
        }

        .signature-line {
            border-top: 1px solid #333;
            padding-top: 7px;
            margin-top: 35px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }

        .small {
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="company-name">
            MADINA GROUP
        </div>

        <div class="document-title">
            GATE PASS
        </div>

        <div class="document-subtitle">
            Gate Management System
        </div>
    </div>


    <table class="info-table">

        <tr>
            <td class="label">
                Pass No
            </td>

            <td class="value">
                <strong>
                    {{ $gatePass->pass_no }}
                </strong>
            </td>

            <td class="label">
                Gate Pass Type
            </td>

            <td class="value">
                @if ($gatePass->gate_pass_type === 'person')
                    Person
                @elseif ($gatePass->gate_pass_type === 'material')
                    Material
                @elseif ($gatePass->gate_pass_type === 'person_material')
                    Person + Material
                @else
                    {{ $gatePass->gate_pass_type }}
                @endif
            </td>
        </tr>

        <tr>
            <td class="label">
                Requested By
            </td>

            <td class="value">
                {{ $gatePass->requester?->name ?? '--' }}
            </td>

            <td class="label">
                Status
            </td>

            <td class="value">
                <span class="status">
                    {{ ucfirst($gatePass->status) }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="label">
                Department
            </td>

            <td class="value">
                {{ $gatePass->department_name }}
            </td>

            <td class="label">
                Designation
            </td>

            <td class="value">
                {{ $gatePass->designation_name }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Expected Exit
            </td>

            <td class="value">
                {{ $gatePass->expected_exit_at ?? '--' }}
            </td>

            <td class="label">
                Expected Return
            </td>

            <td class="value">
                {{ $gatePass->expected_return_at ?? '--' }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Purpose
            </td>

            <td colspan="3">
                {{ $gatePass->purpose }}
            </td>
        </tr>

    </table>


    @if (
        in_array(
            $gatePass->gate_pass_type,
            ['material', 'person_material']
        )
    )

        <div class="section-title">
            Material Details
        </div>

        @if ($gatePass->items->count())

            <table class="items-table">

                <thead>
                    <tr>
                        <th style="width: 5%;">
                            SL
                        </th>

                        <th style="width: 25%;">
                            Product / Material
                        </th>

                        <th style="width: 10%;">
                            Quantity
                        </th>

                        <th style="width: 10%;">
                            Unit
                        </th>

                        <th style="width: 15%;">
                            Asset No
                        </th>

                        <th style="width: 15%;">
                            Serial No
                        </th>

                        <th style="width: 20%;">
                            Remarks
                        </th>
                    </tr>
                </thead>

                <tbody>

                    @foreach ($gatePass->items as $index => $item)

                        <tr>

                            <td class="text-center">
                                {{ $index + 1 }}
                            </td>

                            <td>
                                {{ $item->product_name }}
                            </td>

                            <td class="text-right">
                                {{ $item->quantity }}
                            </td>

                            <td>
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

            <div class="remarks-box">
                No material items.
            </div>

        @endif

    @endif


    <div class="section-title">
        Remarks
    </div>

    <div class="remarks-box">
        {{ $gatePass->remarks ?? 'No remarks.' }}
    </div>


    <table class="signature-table">

        <tr>

            <td>
                <div class="signature-line">
                    Requested By
                </div>

                <div class="small">
                    {{ $gatePass->requester?->name ?? '--' }}
                </div>
            </td>

            <td>
                <div class="signature-line">
                    Authorized By
                </div>

                <div class="small">
                    Signature &amp; Seal
                </div>
            </td>

        </tr>

    </table>


    <div class="footer">
        Generated from Madina Group
        | Pass No: {{ $gatePass->pass_no }}
    </div>

</body>

</html>