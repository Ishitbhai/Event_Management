<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch services
    $services = [];
    $query = "SELECT service_title, service_image, service_description FROM services";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $services[] = $row;
        }
    }

    // Fetch Why Choose Aone Hub values dynamically
    $values = [];
    $values_query = "SELECT why_title, why_description FROM why_aone_hub";
    $values_result = mysqli_query($conn, $values_query);
    if ($values_result) {
        while ($row = mysqli_fetch_assoc($values_result)) {
            $values[] = $row;
        }
    }
?>

<link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="css/services.css">


<div class="services-hero fade-in-up">
    <h1>Our Services</h1>
    <p>
        At Aone Hub, we curate, plan, and execute exceptional events.<br>
        From visionary concepts to seamless delivery—explore our classic suite of services.
    </p>
</div>

<section class="services-list-section">
    <div class="container">
        <div class="text-center mb-5 fade-in-up"><span class="section-title">Service Offerings</span></div>
        <div class="row justify-content-center">
        <?php if (!empty($services)): ?>
            <?php 
            $delay = 0;
            foreach ($services as $service): 
                $delay += 0.07; // Animation delay
            ?>
                <div class="col-12 col-sm-6 col-lg-4 d-flex align-items-stretch">
                    <div class="service-card fade-in-up" style="animation-delay: <?= sprintf('%.2f', $delay) ?>s;">
                        <div class="service-icon-image">
                        <?php
                            if (preg_match('/^fa[srlb]? /', $service['service_image'])) {
                                echo '<i class="' . htmlspecialchars($service['service_image']) . '" aria-hidden="true"></i>';
                            } else {
                                echo '<img src="images/' . htmlspecialchars($service['service_image']) . '" alt="' . htmlspecialchars($service['service_title']) . ' Icon" />';
                            }
                        ?>
                        </div>
                        <h3><?= htmlspecialchars($service['service_title']) ?></h3>
                        <p><?= htmlspecialchars($service['service_description']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
                <div class="col-12"><div class="alert alert-secondary text-center mt-3">No services available at the moment.</div></div>
        <?php endif; ?>
        </div>
    </div>
</section>

<section class="values-section">
    <div class="container">
        <h2 class="fade-in-up mb-4">Why Choose Aone Hub?</h2>
        <div class="row values-grid justify-content-center">
        <?php if (!empty($values)): ?>
            <?php 
            $vdelay = 0;
            foreach ($values as $value): 
                $vdelay += 0.08;
            ?>
            <div class="col-12 col-md-6 col-lg-4 d-flex">
                <div class="value-card fade-in-up" style="animation-delay: <?= sprintf('%.2f', $vdelay) ?>s;">
                    <strong><?= htmlspecialchars($value['why_title']) ?>:</strong><br>
                    <?= htmlspecialchars($value['why_description']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="value-card">No reasons available at the moment.</div>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>

<?php require('footer.php'); ?>
