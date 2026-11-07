   @php
                    $days=Helper::calculateDays(date('Y-m-d'),$start_date);


                    $payment_interval=ModelHelper::getDataFromSetting("payment_interval");
                    if($payment_interval){
                    }else{
                        $payment_interval=1;
                    }
                    $total_payment=$payment_interval;
                   
                    if($payment_interval==1){
                            $first_amount=$remaining_total_amount;

                            $first_message="Total Amount to be Paid";
                            $total_payment=1;
                            $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                    }
                    elseif($payment_interval==2){

                         $second_days=ModelHelper::getDataFromSetting('second_min_total_days');

                        if($days>=$second_days){

                            $second_per=ModelHelper::getDataFromSetting('second_payment_per');

                            $second_amount=round(($remaining_total_amount*$second_per)/100,2);
                            $first_amount=$remaining_total_amount-$second_amount;

                            $second_days=ModelHelper::getDataFromSetting('second_how_many_days');
                            $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));


                            $first_message="Initial Deposit to be Paid";
                            $second_message="Final Remaining Amount Due on ".$second_date;
                       
                            $total_payment=2;
                            $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                            $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 

                        }else{
                            $first_amount=$remaining_total_amount;

                            $first_message="Total Amount to be Paid";
                            $total_payment=1;
                            $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                        } 
                    }
                    elseif($payment_interval==3){
                        $second_days=ModelHelper::getDataFromSetting('second_min_total_days');
                        $third_days=ModelHelper::getDataFromSetting('third_min_total_days');

                        if($days>=$third_days){

                            $second_per=ModelHelper::getDataFromSetting('second_third_payment_per');
                            $third_per=ModelHelper::getDataFromSetting('third_payment_per');


                            $second_amount=round(($remaining_total_amount*$second_per)/100,2);
                            $third_amount=round(($remaining_total_amount*$third_per)/100,2);
                            $first_amount=$remaining_total_amount-$second_amount-$third_amount;


                            $second_days=ModelHelper::getDataFromSetting('second_third_how_many_days');
                            $third_days=ModelHelper::getDataFromSetting('third_how_many_days');

                            $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));
                            $third_date=date('F jS, Y',strtotime('- '.$third_days.' days',strtotime($start_date)));
                            $total_payment=3;
                            $first_message="Initial Deposit to be Paid";
                            $second_message="Second Remaining Amount Due on ".$second_date;
                            $third_message="Final Remaining Amount Due on ".$third_date;

                            $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                            $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 
                            $amount_data[]=["amount"=>$third_amount,"type"=>"third","message"=>$third_message]; 

                        }elseif($days>=$second_days){

                            $second_per=ModelHelper::getDataFromSetting('second_payment_per');

                            $second_amount=round(($remaining_total_amount*$second_per)/100,2);
                            $first_amount=$remaining_total_amount-$second_amount;

                            $second_days=ModelHelper::getDataFromSetting('second_how_many_days');
                            $second_date=date('F jS, Y',strtotime('- '.$second_days.' days',strtotime($start_date)));


                            $first_message="Initial Deposit to be Paid";
                            $second_message="Final Remaining Amount Due on ".$second_date;
                       
                            $total_payment=2;
                            $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                            $amount_data[]=["amount"=>$second_amount,"type"=>"second","message"=>$second_message]; 

                        }else{
                            $first_amount=$remaining_total_amount;
                            $total_payment=1;
                            $first_message="Total Amount to be Paid";

                            $amount_data[]=["amount"=>$first_amount,"type"=>"first","message"=>$first_message];
                        }   
                    }
                    @endphp