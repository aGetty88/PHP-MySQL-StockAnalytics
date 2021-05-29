<?php
    // Initialize.
    require_once('database.php');
    global $db;    
    session_start();
    if( !isset( $_SESSION['symbol'])) { $_SESSION['symbol'] = ''; }
    $symbol = $_SESSION['symbol']; 
    if( !isset( $_SESSION['outputsize'])) { $_SESSION['outputsize'] = 'compact'; }
    $outputsize = $_SESSION['outputsize'];
    if( !isset( $_SESSION['apikey'])) { $_SESSION['apikey'] = '3LY8MMK7N4ZEP57D'; }
    $apikey = $_SESSION['apikey']; 
    if( !isset( $_SESSION['valid_time_series_info'])) { $_SESSION['valid_time_series_info'] = false; }
    $valid_time_series_info = $_SESSION['valid_time_series_info'];          
    if( !isset( $_SESSION['field'])) { $_SESSION['field'] = 4; }
    $field = $_SESSION['field'];     
    if( !isset( $_SESSION['start_timestamp_str'])) { $_SESSION['start_timestamp_str'] = 'YYYY-MM-DD'; }
    $start_timestamp_str = $_SESSION['start_timestamp_str'];     
    if( !isset( $_SESSION['start_timestamp'])) { $_SESSION['start_timestamp'] = -1; }
    $start_timestamp = $_SESSION['start_timestamp'];        
    if( !isset( $_SESSION['end_timestamp_str'])) { $_SESSION['end_timestamp_str'] = 'YYYY-MM-DD'; }
    $end_timestamp_str = $_SESSION['end_timestamp_str'];     
    if( !isset( $_SESSION['end_timestamp'])) { $_SESSION['end_timestamp'] = -1; }
    $end_timestamp = $_SESSION['end_timestamp'];    
    $min = '';
    $max = '';
    $mean = '';
    $median = '';      
    $error = '';
    
    // Process forms.
        if(isset($_GET['page_form'])) 
        {
            switch ($_GET['page_form'])
            {
            case "1":
                // Get stock data.
                $valid_time_series_info = true;
                $symbol = $_GET['symbol'];
                if (empty($symbol)) {
                     $error = 'Please enter a stock symbol';
                     $valid_time_series_info = false;
                }
                $_SESSION['symbol'] = $symbol; 
                $outputsize = "compact";
                if (isset($_GET['outputsize'])) $outputsize = $_GET['outputsize'];
                if (empty($outputsize) || !($outputsize == "compact" || $outputsize == "full")) {
                     $error = 'Outputsize must be compact or full';
                     $valid_time_series_info = false;
                }
                $_SESSION['outputsize'] = $outputsize;                    
                $apikey = $_GET['apikey'];
                if (empty($apikey)) {
                     $error = 'Please enter an ApiKey';
                     $valid_time_series_info = false;                       
                }
                $_SESSION['apikey'] = $apikey;
                break;
            case "2":
                // Filter.
                $start_timestamp = -1;
                $end_timestamp = -1;
                if(isset($_GET['field_select'])){
                    $field = $_GET['field_select'];
                }
                if(isset($_GET['start_timestamp'])){
                    $start_timestamp_str = $_GET['start_timestamp'];
                    $start_timestamp = strtotime($start_timestamp_str);
                    if ($start_timestamp == false)
                    {
                        $start_timestamp = -1;
                        $error = 'Invalid start date';
                    }
                }
                if(isset($_GET['end_timestamp'])){
                    $end_timestamp_str = $_GET['end_timestamp'];
                    $end_timestamp = strtotime($end_timestamp_str);
                    if ($end_timestamp == false)
                    {
                        $start_timestamp = -1;
                        $end_timestamp = -1;
                        $error = 'Invalid end date';
                    }
                }
                if ($start_timestamp > $end_timestamp) {
                    $start_timestamp = -1;
                    $end_timestamp = -1;
                    $error = "Invalid date range";    
                }            
                $_SESSION['field'] = $field;
                $_SESSION['start_timestamp'] = $start_timestamp;
                $_SESSION['start_timestamp_str'] = $start_timestamp_str;
                $_SESSION['end_timestamp'] = $end_timestamp;
                $_SESSION['end_timestamp_str'] = $end_timestamp_str;           
                break;
            default:
                $error = "Invalid form!";
        }
        
        // Get stock info?
        if ($valid_time_series_info && empty($error))
        {
            // Access Alpha Vantage for stock time series csv data.
            $url = "https://www.alphavantage.co/query" . "?function=TIME_SERIES_DAILY" . "&symbol=" . $symbol . "&outputsize=" . $outputsize . "&apikey=" . $apikey . "&datatype=csv";
            $timeseries = file_get_contents($url);
            if(strpos($timeseries, "Error") !== false){
                $error = $timeseries;
                $valid_time_series_info = false;
            }                
        }
        $_SESSION['valid_time_series_info'] = $valid_time_series_info;
        if ($valid_time_series_info && empty($error))
        {
            $timeseries_array = preg_split('/[ \n]/', $timeseries);

            // Clear table.
            $db->beginTransaction();
            $query = 'TRUNCATE TABLE StockTimeSeries';
            $statement = $db->prepare($query);
            $statement->execute();
            $statement->closeCursor();

            // Initialize statistics.
            $minwork = -1.0;
            $maxwork = -1.0;
            $sumwork = 0.0;
            $countwork = 0;
            $numwork = array();

            // Insert time series.                
            $count = count($timeseries_array);
            for ($i = 1; $i < $count; $i++)
            {
                if (strlen($timeseries_array[$i]) > 0)
                {
                    $timeseries_fields = preg_split('/[,]/', $timeseries_array[$i]); 
                    $timestamp = strtotime($timeseries_fields[0]);
                    if ($start_timestamp != -1) 
                    {    
                        if ($timestamp < $start_timestamp || $timestamp > $end_timestamp) {
                            continue;
                        }
                    }
                    $query = 'INSERT INTO StockTimeSeries
                                   (timestamp, open, high, low, close, volume)
                                VALUES
                                   (:timestampx, :openx, :highx, :lowx, :closex, :volumex)';
                    $statement = $db->prepare($query);
                    $statement->bindValue(':timestampx', date('Y-m-d', $timestamp));
                    $statement->bindValue(':openx', $timeseries_fields[1]);
                    $statement->bindValue(':highx', $timeseries_fields[2]);
                    $statement->bindValue(':lowx', $timeseries_fields[3]);
                    $statement->bindValue(':closex', $timeseries_fields[4]);
                    $statement->bindValue(':volumex', $timeseries_fields[5]);       
                    $statement->execute();
                    $statement->closeCursor();
                    $n = floatval($timeseries_fields[$field]);
                    $sumwork += $n;
                    array_push($numwork, $n);
                    if ($minwork < 0.0 || $n < $minwork) {
                        $minwork = $n;
                    }
                    if ($maxwork < 0.0 || $n > $maxwork) {
                        $maxwork = $n;
                    }          
                    $countwork++;                              
                }
            }
            $db->commit();
            if ($countwork > 0) {
                if ($field < 5) {
                    $min = round($minwork, 2);
                    $max = round($maxwork, 2);
                    sort($numwork);
                    $mid = intval(count($numwork) / 2);
                    $median = round($numwork[$mid], 2);
                } else {
                    $min = intval($minwork);
                    $max = intval($maxwork);
                    sort($numwork);
                    $mid = intval(count($numwork) / 2);
                    $median = intval($numwork[$mid]);                    
                }
                $mean = round($sumwork / $countwork, 2);
             } else {
              $min = "";
              $max = "";
              $mean = "";
              $median = "";      
            }                 
        }        
    }
?> 
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Stock analytics</title>
<link rel="stylesheet" type="text/css" href="main.css">
</head>
<body>
    <main>
        <h1>Stock Analytics</h1>
        <h4>Specification:</h4>
        <form action="stock_analytics.php" method="get">
            <div id="data">
                <label>Stock symbol:</label>
                <input type="text" name="symbol"
                       value="<?php echo htmlspecialchars($symbol); ?>">
                <br>

                <label>Outputsize:</label>
                <input type="text" name="outputsize"
                       value="<?php echo htmlspecialchars($outputsize); ?>">
                <br>

                <label>ApiKey:</label>
                <input type="text" name="apikey"
                       value="<?php echo htmlspecialchars($apikey); ?>">
                <br>
            </div>
            <input type="hidden" name="page_form" value="1">
            <div id="buttons">
                <label>&nbsp;</label>
                <input type="submit" value="Get"><br>
            </div>
        </form>
        <h4>Filters:</h4>   
        <form action="stock_analytics.php" method="get">
            <div id="data">    
                <label>Field:</label>          
                <select name="field_select" style="font-family: Arial, Helvetica, sans-serif; font-size:15px;">
                    <option value = "1"<?php if ($field == 1) echo ' selected'; ?>>open</option>
                    <option value = "2"<?php if ($field == 2) echo ' selected'; ?>>high</option>
                    <option value = "3"<?php if ($field == 3) echo ' selected'; ?>>low</option>
                    <option value = "4"<?php if ($field == 4) echo ' selected'; ?>>close</option>              
                    <option value = "5"<?php if ($field == 5) echo ' selected'; ?>>volume</option>
                </select>
                <br>            
                <label>Start:</label>
                <input type="text" name="start_timestamp"
                       value="<?php echo htmlspecialchars($start_timestamp_str); ?>">             
                <br>
                <label>End:</label>
                <input type="text" name="end_timestamp"
                       value="<?php echo htmlspecialchars($end_timestamp_str); ?>">
                <br>
            </div>
            <input type="hidden" name="page_form" value="2">
            <div id="buttons">
                <label>&nbsp;</label>
                <input type="submit" value="Filter"><br>
            </div>
        </form>
        <h4>Statistics:</h4>   
            <div id="data">
                <label>Minimum:</label>
                <input type="text" name="min"
                       value="<?php echo htmlspecialchars($min); ?>" DISABLED>            
                <br>
                 <label>Maximum:</label>
                <input type="text" name="max"
                       value="<?php echo htmlspecialchars($max); ?>" DISABLED> 
                <br>               
                <label>Mean:</label>
                <input type="text" name="mean"
                       value="<?php echo htmlspecialchars($mean); ?>" DISABLED> 
                <br>
                 <label>Median:</label>
                <input type="text" name="median"
                       value="<?php echo htmlspecialchars($median); ?>" DISABLED> 
                <br>           
            </div>    
        <h4>Time series:</h4>
        <?php if (!empty($error)) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php exit; } ?> 
        <?php
            // Populate time series table?
            if ($valid_time_series_info) {
                $query = 'SELECT * FROM StockTimeSeries';
                $statement = $db->prepare($query);
                $statement->execute();
                $stocks = $statement->fetchAll();
                $statement->closeCursor();
            } else {
                echo '<p class="error">No time series data!</p>';
                exit;
            }        
        ?>
            <table border="1">
                <tr>
                    <th>timestamp</th>
                    <th>open</th>
                    <th>high</th>
                    <th>low</th>
                    <th>close</th>
                    <th>volume</th>                
                </tr>

                <?php foreach ($stocks as $stock) : ?>
                <tr>
                    <td><?php echo $stock['timestamp']; ?></td>
                    <td><?php echo $stock['open']; ?></td>
                    <td><?php echo $stock['high']; ?></td>
                    <td><?php echo $stock['low']; ?></td>
                    <td><?php echo $stock['close']; ?></td>
                    <td><?php echo $stock['volume']; ?></td>                
                </tr>
                <?php endforeach; ?>
            </table>         
    </main>  
</body>
</html>
