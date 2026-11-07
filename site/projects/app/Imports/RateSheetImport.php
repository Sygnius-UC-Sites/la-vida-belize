<?php 
namespace App\Imports;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\HasReferencesToOtherSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Models\PropertyRate;
use App\Models\Property;
use Session;


class RateSheetImport implements ToCollection,WithCalculatedFormulas, HasReferencesToOtherSheets,WithColumnFormatting{
  
    public function columnFormats(): array{
        return ['A' => NumberFormat::FORMAT_DATE_XLSX15    ];
    }
  
    public function collection(Collection $rows){
        foreach($rows as $key=>$row){
            if($key==0){continue;}
            if($row[0]==''){continue;}
            if($row[1]==''){continue;}
            $data=[];
            if($row[0]!=''){
                 if(gettype($row[0])=="integer"){
                    $data['single_date']=Date::excelToDateTimeObject($row[0])->format('Y-m-d');;
                    $data['single_date_timestamp']=strtotime(Date::excelToDateTimeObject($row[0])->format('Y-m-d'));
                }else{
                    continue;   
                }
            }
            if($row[1]){
                $data['price']=$row[1];
            }
            $data['property_id']=Session::get("property_id");
            PropertyRate::create($data);
        }
    }
}