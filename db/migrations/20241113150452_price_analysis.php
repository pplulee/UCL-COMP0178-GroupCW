<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PriceAnalysis extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up(): void
    {
        $sql = <<<SQL
         -- 1. Create table for historical price trend analysis
        CREATE TABLE IF NOT EXISTS price_trend_analysis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT,
            analysis_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            time_period VARCHAR(20),  
            avg_price DECIMAL(10,2),
            max_price DECIMAL(10,2),
            min_price DECIMAL(10,2),
            price_volatility DECIMAL(5,2),
            bid_frequency INT,
            FOREIGN KEY (item_id) REFERENCES AuctionItem(id) ON DELETE CASCADE
        );

        -- 2. Create table for similar items reference
        CREATE TABLE IF NOT EXISTS similar_items_reference (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_item_id INT,
            reference_item_id INT,
            similarity_score DECIMAL(5,2),
            final_price DECIMAL(10,2),
            category_match BOOLEAN,
            end_date DATE,
            FOREIGN KEY (source_item_id) REFERENCES AuctionItem(id) ON DELETE CASCADE,
            FOREIGN KEY (reference_item_id) REFERENCES AuctionItem(id) ON DELETE CASCADE
        );

        -- 3. Create market heat analysis view
        CREATE OR REPLACE VIEW market_heat_analysis AS
        SELECT 
            ai.id AS item_id,
            ai.category_id,
            COUNT(DISTINCT b.user_id) AS unique_bidders,
            COUNT(b.id) AS total_bids,
            AVG(b.bid_price - b_prev.bid_price) AS avg_bid_increment,
            MAX(b.bid_price) - MIN(b.bid_price) AS price_range,
            COUNT(b.id) / TIMESTAMPDIFF(HOUR, MIN(b.bid_time), MAX(b.bid_time)) AS bid_frequency_per_hour
        FROM 
            AuctionItem ai
            LEFT JOIN bid b ON ai.id = b.auction_item_id
            LEFT JOIN bid b_prev ON b.auction_item_id = b_prev.auction_item_id 
                AND b.bid_time > b_prev.bid_time
        GROUP BY 
            ai.id, ai.category_id;

        -- 4. Create smart price suggestion function
        CREATE FUNCTION get_smart_price_suggestion(
            p_item_id INT
        ) 
        RETURNS DECIMAL(10,2)
        DETERMINISTIC
        BEGIN
            DECLARE v_suggested_price DECIMAL(10,2);
            DECLARE v_category_id INT;
            DECLARE v_current_price DECIMAL(10,2);
            DECLARE v_market_heat DECIMAL(5,2);
            DECLARE v_similar_avg_price DECIMAL(10,2);
            
            SELECT 
                category_id, 
                current_price
            INTO 
                v_category_id,
                v_current_price
            FROM 
                AuctionItem 
            WHERE 
                id = p_item_id;
            
            SELECT 
                (unique_bidders * 0.3 + bid_frequency_per_hour * 0.7)
            INTO v_market_heat
            FROM 
                market_heat_analysis
            WHERE 
                item_id = p_item_id;
            
            SELECT 
                AVG(final_price)
            INTO v_similar_avg_price
            FROM 
                similar_items_reference
            WHERE 
                source_item_id = p_item_id
                AND end_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY);
            
            SET v_suggested_price = (
                v_current_price * 0.4 +
                COALESCE(v_similar_avg_price, v_current_price) * 0.4 +
                (COALESCE(v_market_heat, 1) * v_current_price * 0.2)
            );
            
            RETURN ROUND(v_suggested_price, 2);
        END;

        -- 5. Create table for price prediction accuracy tracking
        CREATE TABLE IF NOT EXISTS price_prediction_accuracy (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT,
            prediction_time TIMESTAMP,
            predicted_price DECIMAL(10,2),
            actual_final_price DECIMAL(10,2),
            prediction_error_rate DECIMAL(5,2),
            market_conditions TEXT,
            FOREIGN KEY (item_id) REFERENCES AuctionItem(id) ON DELETE CASCADE
        );

        -- 6. Create trigger for daily price analysis
        CREATE TRIGGER after_bid_update_analysis
        AFTER INSERT ON bid
        FOR EACH ROW
        BEGIN
            INSERT INTO price_trend_analysis (
                item_id, 
                time_period, 
                avg_price, 
                max_price, 
                min_price,
                bid_frequency
            )
            SELECT 
                NEW.auction_item_id,
                '24h',
                AVG(bid_price),
                MAX(bid_price),
                MIN(bid_price),
                COUNT(*)
            FROM bid
            WHERE 
                auction_item_id = NEW.auction_item_id
                AND bid_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
                
            INSERT INTO similar_items_reference (
                source_item_id,
                reference_item_id,
                similarity_score,
                final_price,
                category_match,
                end_date
            )
            SELECT 
                NEW.auction_item_id,
                ai.id,
                CASE 
                    WHEN ai.category_id = (SELECT category_id FROM AuctionItem WHERE id = NEW.auction_item_id) 
                    THEN 0.8
                    ELSE 0.4
                END,
                ai.current_price,
                ai.category_id = (SELECT category_id FROM AuctionItem WHERE id = NEW.auction_item_id),
                ai.end_date
            FROM 
                AuctionItem ai
            WHERE 
                ai.status = 'closed'
                AND ai.end_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                AND ai.id != NEW.auction_item_id
            LIMIT 5;
        END;
        SQL;

        $this->execute($sql);
    }

    public function down(): void
    {
        $sql = <<<SQL
        DROP TRIGGER IF EXISTS after_bid_update_analysis;
        DROP FUNCTION IF EXISTS get_smart_price_suggestion;
        DROP VIEW IF EXISTS market_heat_analysis;
        DROP TABLE IF EXISTS price_prediction_accuracy;
        DROP TABLE IF EXISTS similar_items_reference;
        DROP TABLE IF EXISTS price_trend_analysis;
        SQL;

        $this->execute($sql);
    }
}
