<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/
	try {
		date_default_timezone_set('America/Caracas');
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('config.ini');
		echo '==============================================================================' . "\r\n";
		echo 'Inicio de Sincronizacion de Promociones ' . date('d-m-y H:i:s a') . "\r\n";
		if ($params === false) {
			// exeption leyen archivo config
			throw new \Exception("Error reading database configuration file");
		}
		// connect to the sql server database
		if($params['instance']!='') {
			$conStr = sprintf("sqlsrv:Server=%s\%s;",
				$params['host_sql'],
				$params['instance']);
		} else {
			$conStr = sprintf("sqlsrv:Server=%s,%d;",
				$params['host_sql'],
				$params['port_sql']);
		}
		$connec = new \PDO($conStr, $params['user_sql'], $params['password_sql']);
		// Obtener datos de Promociones por Articulo
		$sql = "SELECT cab.CODIGO, cab.DIAS, det.ARTICULO, cab.FECHAI, cab.FECHAF,
					(CASE det.TIPOPREMIO
						WHEN 1 THEN (det.DESCUENTO/(1+(art.IMPUESTO/100)))
						WHEN 2 THEN ((100-det.DESCUENTO)*art.PRECIO1)/100
						WHEN 3 THEN art.PRECIO1-det.DESCUENTO
					END) AS PROMO,
					art.departamento, art.grupo, art.subgrupo
				FROM BDES.dbo.BIPromociones AS cab
				LEFT JOIN BDES.dbo.BIPromocionesItems AS det ON det.PROMO = cab.CODIGO
				INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.ARTICULO
				WHERE cab.ACTIVA = 1 AND cab.TIPOPROMO = 5
				AND ((CAST(GETDATE() AS DATE) BETWEEN CAST(cab.FECHAI AS DATE) AND CAST(cab.FECHAF AS DATE)) OR
					(DATEPART(YEAR, cab.FECHAI) = 1900 AND cab.DIAS != 0))";
		$sql = $connec->query($sql);
		if(!$sql) {
			print_r($connec->errorInfo()); print_r($sql);
		} else {
			// Chequeo de Promociones por Articulo
			$diaac = (date('w')==0 ? 7 : date('w'));
			$datos = [];
			while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
				if($row['DIAS']!=0 && array_search($diaac, diasSem($row['DIAS']))!==false) {
					$datos[] = [
						'articulo'     => $row['ARTICULO'],
						'promo'        => round($row['PROMO'], 2),
						'codigo'       => $row['CODIGO'],
						'departamento' => $row['departamento'],
						'grupo'        => $row['grupo'],
						'subgrupo'     => $row['subgrupo'],
						'fechai'       => $row['FECHAI'],
						'fechaf'       => $row['FECHAF'],
					];
				}
				if($row['DIAS']==0) {
					$datos[] = [
						'articulo'     => $row['ARTICULO'],
						'promo'        => round($row['PROMO'], 2),
						'codigo'       => $row['CODIGO'],
						'departamento' => $row['departamento'],
						'grupo'        => $row['grupo'],
						'subgrupo'     => $row['subgrupo'],
						'fechai'       => $row['FECHAI'],
						'fechaf'       => $row['FECHAF'],
					];
				}
			}
			// Obtener datos de Promociones General
			$sql = "SELECT cab.CODIGO, cab.DIAS, art.codigo AS ARTICULO, cab.FECHAI, cab.FECHAF,
						(CASE WHEN det2.TIPOC=1 THEN
							(((100-det2.DESCUENTO)*art.PRECIO1)/100) ELSE 
							(((100-det.DESCUENTO)*art.PRECIO1)/100) END) AS PROMO,
						art.departamento, art.grupo, art.subgrupo
					FROM BDES.dbo.BIPromociones AS cab
					LEFT JOIN BDES.dbo.BIPromocionesCateg AS det ON det.PROMO = cab.CODIGO
					INNER JOIN BDES.dbo.BIPromocionesCategItems AS det2 ON det2.PROMO = cab.CODIGO
					INNER JOIN BDES.dbo.ESARTICULOS AS art ON
						((art.codigo = det2.CODIGO AND det2.TIPOC = 1) OR 
						(art.departamento = det2.CODIGO AND det2.TIPOC = 2 AND det.DESCUENTO > 0) OR
						(art.grupo = det2.CODIGO AND det2.TIPOC = 3) OR
						(art.subgrupo = det2.CODIGO AND det2.TIPOC = 4))
					WHERE cab.ACTIVA = 1 AND cab.TIPOPROMO = 0
					AND ((CAST(GETDATE() AS DATE) BETWEEN CAST(cab.FECHAI AS DATE) AND CAST(cab.FECHAF AS DATE))
					OR  (DATEPART(YEAR, cab.FECHAI) = 1900 AND cab.DIAS != 0))";
			$sql = $connec->query($sql);
			if(!$sql) {
				print_r($connec->errorInfo()); print_r($sql);
			} else {
				// Chequeo de Promociones General
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					if($row['DIAS']!=0 && array_search($diaac, diasSem($row['DIAS']))!==false) {
						$datos[] = [
							'articulo'     => $row['ARTICULO'],
							'promo'        => round($row['PROMO'], 2),
							'codigo'       => $row['CODIGO'],
							'departamento' => $row['departamento'],
							'grupo'        => $row['grupo'],
							'subgrupo'     => $row['subgrupo'],
							'fechai'       => $row['FECHAI'],
							'fechaf'       => $row['FECHAF'],
						];
					}
					if($row['DIAS']==0) {
						$datos[] = [
							'articulo'     => $row['ARTICULO'],
							'promo'        => round($row['PROMO'], 2),
							'codigo'       => $row['CODIGO'],
							'departamento' => $row['departamento'],
							'grupo'        => $row['grupo'],
							'subgrupo'     => $row['subgrupo'],
							'fechai'       => $row['FECHAI'],
							'fechaf'       => $row['FECHAF'],
						];
					}
				}
				if(count($datos) > 0) {
					// Ordenar datos de Promociones
					echo 'Articulos obtenidos ' . count($datos) . ' ' . date('d-m-y H:i:s a') . "\r\n";
					$promos = '';
					foreach ($datos as $key => $value) {
						$promos .= $value['codigo'] . ',';
					}
					$promos = substr($promos, 0, -1);
					// Se eliminan los registros excluidos
					$sql = "SELECT PROMO, CODIGO, TIPOC
							FROM BDES.dbo.BIPromocionesCategItems
							WHERE INCLUIDA = 0
							AND PROMO IN($promos)
							ORDER BY PROMO";
					$sql = $connec->query($sql);
					if(!$sql) { 
						print_r($connec->errorInfo()); print_r($sql);
					} else {
						$excluidos = [];
						while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
							$excluidos[] = [
								'promo'  => $row['PROMO'],
								'codigo' => $row['CODIGO'],
								'tipoc'  => $row['TIPOC'],
							];
						}
						foreach ($excluidos as $valor) {
							switch ($valor['tipoc']) {
								case 1:
									while ( $find = array_search($valor['codigo'], array_column($datos, 'articulo'), true) ) {
										$datos[$find]['articulo'] = 'eliminar';
									}
									break;
								case 2:
									while ( $find = array_search($valor['codigo'], array_column($datos, 'departamento'), true) ) {
										$datos[$find]['articulo'] = 'eliminar';
										$datos[$find]['departamento'] = 'eliminar';
									}
									break;
								case 3:
									while ( $find = array_search($valor['codigo'], array_column($datos, 'grupo'), true) ) {
										$datos[$find]['articulo'] = 'eliminar';
										$datos[$find]['grupo'] = 'eliminar';
									}
									break;
								case 4:
									while ( $find = array_search($valor['codigo'], array_column($datos, 'subgrupo'), true) ) {
										$datos[$find]['articulo'] = 'eliminar';
										$datos[$find]['subgrupo'] = 'eliminar';
									}
									break;
							}
						}
						$datos2 = [];
						foreach ($datos as $key => $value) {
							if($value['articulo']!='eliminar') {
								$datos2[] = $datos[$key];
							}
						}
						$datos = $datos2;
						foreach ($datos as $clave => $fila) {
							$orden1[$clave] = $fila['articulo'];
							$orden2[$clave] = $fila['promo'];
						}
						array_multisort($orden1, SORT_ASC, $orden2, SORT_ASC, $datos);
						// Elimina Promociones repetidas dejando las de menor precio;
						$datos = unique_multidim_array($datos,'articulo');
						// Se agregar los registros excluidos con descuento != 0
						$sql = "SELECT PROMO, CODIGO, TIPOC, DESCUENTO
								FROM BDES.dbo.BIPromocionesCategItems
								WHERE INCLUIDA = 0
								AND DESCUENTO > 0
								AND PROMO IN($promos)
								ORDER BY PROMO";
						$sql = $connec->query($sql);
						if(!$sql) { 
							print_r($connec->errorInfo()); print_r($sql);
						} else {
							$incluir = [];
							while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
								$incluir[] = [
									'promo'  => $row['PROMO'],
									'codigo' => $row['CODIGO'],
									'tipoc'  => $row['TIPOC'],
									'dscto'  => $row['DESCUENTO']
								];
							}
							$artincluir = [];
							foreach ($incluir as $valor) {
								switch ($valor['tipoc']) {
									case 1:
										$sql = "SELECT " . $valor['promo'] . " AS CODIGO, " . $valor['codigo'] . " AS ARTICULO,
													((100-". $valor['dscto'] . ")*art.PRECIO1)/100 AS PROMO,
													art.departamento, art.grupo, art.subgrupo
												FROM BDES.dbo.ESARTICULOS AS art 
												WHERE art.codigo = " . $valor['codigo'];
										break;
									case 2:
										$sql = "SELECT " . $valor['promo'] . " AS CODIGO, art.codigo AS ARTICULO,
													((100-". $valor['dscto'] . ")*art.PRECIO1)/100 AS PROMO,
													art.departamento, art.grupo, art.subgrupo
												FROM BDES.dbo.ESARTICULOS AS art 
												WHERE art.departamento = " . $valor['codigo'];
										break;
									case 3:
										$sql = "SELECT " . $valor['promo'] . " AS CODIGO, art.codigo AS ARTICULO,
													((100-". $valor['dscto'] . ")*art.PRECIO1)/100 AS PROMO,
													art.departamento, art.grupo, art.subgrupo
												FROM BDES.dbo.ESARTICULOS AS art 
												WHERE art.grupo = " . $valor['codigo'];
										break;
									case 4:
										$sql = "SELECT " . $valor['promo'] . " AS CODIGO, art.codigo AS ARTICULO,
													((100-". $valor['dscto'] . ")*art.PRECIO1)/100 AS PROMO,
													art.departamento, art.grupo, art.subgrupo
												FROM BDES.dbo.ESARTICULOS AS art 
												WHERE art.subgrupo = " . $valor['codigo'];
										break;
								}
								$sql = $connec->query($sql);
								if(!$sql) {
									print_r($connec->errorInfo()); print_r($sql);
								} else {
									while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
										$artincluir[] = [
											'articulo'     => $row['ARTICULO'],
											'promo'        => round($row['PROMO'], 2),
											'codigo'       => $row['CODIGO'],
											'departamento' => $row['departamento'],
											'grupo'        => $row['grupo'],
											'subgrupo'     => $row['subgrupo']
										];
									}
								}
							}
							foreach ($artincluir as $row) {
								$datos[] = [
									'articulo'     => $row['articulo'],
									'promo'        => $row['promo'],
									'codigo'       => $row['codigo'],
									'departamento' => $row['departamento'],
									'grupo'        => $row['grupo'],
									'subgrupo'     => $row['subgrupo']
								];
							}
							// Crea tabla temporal de Promociones
							echo 'Articulos a modificar: ' . count($datos) . ' ' . date('d-m-y H:i:s a') . "\r\n";
							// echo '';
							// $k = 0;
							// $s = '<table border="1">';
							// foreach ( $datos as $r ) {
							// 		$s .= '<tr><td>'.$k.'</td>';
							// 		foreach ( $r as $v ) {
							// 				$s .= '<td>'.$v.'</td>';
							// 		}
							// 		$s .= '</tr>';
							// 		$k++;
							// }
							// $s .= '</table>';
							// echo $s;
							$sql = "CREATE TABLE #ArtPromoTmp(articulo INT, promo DECIMAL(20,3), fechai DATETIME, fechaf DATETIME);
									INSERT INTO #ArtPromoTmp(articulo, promo, fechai, fechaf)
									VALUES ";
							foreach ($datos as $clave => $fila) {
								if($fila['articulo']!='eliminar') {
									$sql.= '('.$fila['articulo'].','.$fila['promo'].",'".str_replace(" ", "T", $fila['fechai'])."','".str_replace(" ", "T", $fila['fechaf'])."'),";
								}
							}
							$sql = substr($sql, 0, -1).'; ';
							// Actualizar ESARTICULOS con las Promociones
							$sql.= "UPDATE BDES.dbo.ESARTICULOS 
									SET preciooferta = temporal.promo, 
										fechainicio = temporal.fechai,
										fechafinal = temporal.fechaf,
										horainicio = CAST(temporal.fechai AS TIME(0)),
										horafinal = CAST(temporal.fechaf AS TIME(0)),
										fechamodificacion = GETDATE()
									FROM (
										SELECT articulo, promo, fechai, fechaf 
										FROM #ArtPromoTmp) AS temporal
									WHERE 
										temporal.articulo = BDES.dbo.ESARTICULOS.codigo
										AND (preciooferta != temporal.promo OR fechafinal != temporal.fechaf
											OR CAST(horafinal AS TIME(0)) != CAST(temporal.fechaf AS TIME(0)) );";
							$sql = $connec->exec($sql);
							if($sql===false) {
								print_r( $connec->errorInfo() );
							} else {
								echo 'Se Modificaron ' . $sql . ' registros en ESARTICULOS' . "\r\n";
							}
						}
					}
				}
			}
		}
		echo 'Fin de Sincronizacion de Promociones ' . date('d-m-y H:i:s a') . "\r\n";
		echo '==============================================================================' . "\r\n";
		$connec = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
	function diasSem($diaspromo) {
		$array = [2, 4, 8, 16, 32, 64, 128];
		$adias = [];
		$dias = [];
		$val = $diaspromo;
		$value = $val;
		buscarval($val, $array, $dias, $value);
		for($i=count($dias);$i>0;$i--){
			$adias[] = (array_search($dias[$i-1], $array))+1;
		}
		
		return ($adias);
	}
	function buscarval(&$valor, $arreglo, &$dias, &$value) {
		if(!array_search($valor, $arreglo)) {
			$valor -= 2;
			if($valor==0) {
				$dias[] = 2;
				return;
			}
			buscarval($valor, $arreglo, $dias, $value);
		} else {
			$dias[] = $valor;
			$valor = $value-$valor;
			$value = $valor;
			if($valor==0) {
				return;
			} else {
				buscarval($valor, $arreglo, $dias, $value);
			}
		}
	}
	function unique_multidim_array($array, $key) {
		$temp_array = array();
		$i = 0;
		$key_array = array();
		foreach($array as $val) {
			if (!in_array($val[$key], $key_array)) {
				$key_array[$i] = $val[$key];
				$temp_array[$i] = $val;
			}
			$i++;
		}
		return $temp_array;
	}
?>