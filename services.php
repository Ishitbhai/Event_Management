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

<link rel="stylesheet" href="css/services.css">

<div class="services-hero">
    <h1>Our Services</h1>
    <p>
        At Aone Hub, we provide a comprehensive suite of event services to turn your vision into reality—<br>
        from planning, and logistics to real-time support.
    </p>
</div>

<!-- SERVICES LIST SECTION -->
<section class="services-list-section">
    <div class="services-wrapper">
        <?php if (!empty($services)): ?>
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-icon-circle">
                        <?php
                            // If service_image is a FontAwesome class, use <i>
                            // If it is an image file path, use <img>
                            if (preg_match('/^fa[srlb]? /', $service['service_image'])) {
                                // FontAwesome icon class
                                echo '<i class="' . htmlspecialchars($service['service_image']) . '"></i>';
                            } else {
                                // Assume it's an image path
                                echo '<img src="' . htmlspecialchars($service['service_image']) . '" alt="' . htmlspecialchars($service['service_title']) . ' Icon" />';
                            }
                        ?>
                    </div>
                    <h3><?= htmlspecialchars($service['service_title']) ?></h3>
                    <p><?= htmlspecialchars($service['service_description']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center;width:100%;">No services available at the moment.</p>
        <?php endif; ?>
    </div>
</section>

<!-- VALUES SECTION -->
<section class="values-section">
    <h2>Why Choose Aone Hub?</h2>
    <div class="values-grid">
        <?php if (!empty($values)): ?>
            <?php foreach ($values as $value): ?>
                <div class="value-card">
                    <strong><?= htmlspecialchars($value['why_title']) ?>:</strong>
                    <?= htmlspecialchars($value['why_description']) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="value-card">No reasons available at the moment.</div>
        <?php endif; ?>
    </div>
</section>

<!-- FontAwesome for icons (if not already included) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<?php
    require('footer.php');
?>
