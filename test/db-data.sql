CREATE TABLE `products` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `name` varchar(255) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(50) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `price` int(11) NOT NULL DEFAULT '0',
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `product_types` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `bids` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `time` datetime NOT NULL,
  `price` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `products` (`id`, `name`, `type_id`, `weight`, `weight_unit`, `keywords`, `price`) VALUES
(1, 'Apple', 1, 100.00, 'g', 'fruit,apple', 100),
(2, 'Orange', 1, 2.5, 'kg', 'fruit,orange', 2000);

INSERT INTO `product_types` (`id`, `name`) VALUES
(1, 'Fruit');

INSERT INTO `bids` (`id`, `time`, `price`, `product_id`) VALUES
(1, '2018-01-01 00:00:00', 100, 1),
(2, '2018-01-01 00:00:00', 2000, 2),
(3, '2018-01-02 00:00:00', 200, 1),
(4, '2018-01-02 00:00:00', 3000, 2),
(5, '2018-01-03 00:00:00', 300, 1),
(6, '2018-01-03 00:00:00', 4000, 2),
(7, '2018-01-04 00:00:00', 400, 1);
