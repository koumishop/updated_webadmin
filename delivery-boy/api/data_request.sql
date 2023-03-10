SELECT DISTINCT
                o.id AS order_id,
                status_order.active_status,
                COUNT(oi.order_id) AS number_article,
                o.total,
                o.delivery_charge,
                o.final_total,
                o.payment_method,
                oi.date_added,
                o.active_status,
                o.delivery_time,
                u.id AS user_id,
                u.name AS user_name,
                u.mobile
            FROM
                orders o
            LEFT JOIN order_items oi ON
                oi.order_id = o.id
            LEFT JOIN users u ON
                u.id = oi.user_id
            LEFT JOIN (
             SELECT order_items.id, order_items.active_status, order_items.order_id
                FROM order_items
                LEFT JOIN orders
                ON orders.id = order_items.order_id
                WHERE order_items.active_status = "delivered"
                GROUP BY order_id
            ) as status_order
            on status_order.order_id = o.id
            WHERE
                oi.delivery_boy_id = 6
            GROUP BY
                o.id
            ORDER BY
                o.id
            DESC