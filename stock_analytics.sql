-- Create the database
DROP DATABASE IF EXISTS StockAnalytics2;
CREATE DATABASE StockAnalytics2;
USE StockAnalytics2;

-- Create stock time series table.
CREATE TABLE StockTimeSeries (
  timestamp   DATE              NOT NULL,
  open        DECIMAL(13, 2)    NOT NULL,
  high        DECIMAL(13, 2)    NOT NULL,
  low         DECIMAL(13, 2)    NOT NULL,
  close       DECIMAL(13, 2)    NOT NULL,
  volume      INT(20)           NOT NULL
);



