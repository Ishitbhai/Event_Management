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

<!-- <link rel="stylesheet" href="css/services.css"> -->
<style>
    /* General Styles */
body {
    background: #F6F7FA !important;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #2b2b2b;
}

.section-title {
    font-size: 2.4rem;
    font-weight: 600;
    letter-spacing: 1px;
    color: #232323;
    margin-bottom: 0.4em;
}

.lead-subtext {
    color: #747884;
    font-size: 1.13em;
}

/* Services Hero Section */
.services-hero {
    background: #fff;
    border-bottom: 1px solid #efefef;
    padding: 56px 0 34px 0;
    text-align: center;
    box-shadow: 0 2px 20px rgba(46,48,64,0.06);
}
.services-hero h1 {
    font-size: 2.8em;
    font-weight: 700;
    letter-spacing: 1.3px;
    margin-bottom: 13px;
    color: #252529;
}
.services-hero p {
    font-size: 1.17em;
    max-width: 620px;
    margin: 0 auto 16px auto;
    line-height: 1.53;
    color: #646464;
}

@media (max-width: 600px) {
    .services-hero h1 {
        font-size: 2em;
    }
    .section-title {
        font-size: 1.53em;
    }
}

/* Animations */
@keyframes fadeInUp {
    0% { opacity: 0; transform: translateY(48px);}
    70% { opacity: 0.75;}
    100% { opacity: 1; transform: translateY(0);}
}
.fade-in-up {
    opacity: 0;
    animation: fadeInUp 1.1s cubic-bezier(.44,0,.56,1) forwards;
}

/* Services List Responsive Grid for ANY number of services */
.services-list-section {
    background: #F6F7FA;
    padding: 50px 0 60px 0;
}

.services-list-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
    column-gap: 34px;
    row-gap: 54px; /* Increased row gap to always give enough space between rows */
    margin: 0 auto;
    max-width: 1200px;
    width: 100%;
    align-items: stretch; /* grid items stretch to match row height */
}

/* Guaranteed space between every row, even if more than 3 cards per row */
@media (min-width: 992px) {
    .services-list-grid {
        row-gap: 54px;
    }
}

@media (max-width: 1080px) {
    .services-list-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        column-gap: 17px;
        row-gap: 38px;
    }
}
@media (max-width: 767px) {
    .services-list-grid {
        grid-template-columns: repeat(auto-fit, minmax(85vw, 1fr));
        gap: 16px;
        row-gap: 18px;
        max-width: 100vw;
        padding: 0 7px;
    }
    .service-card {
        min-width: 85vw;
        max-width: 95vw;
        min-height: 200px;
    }
}
@media (max-width: 520px) {
    .services-list-grid {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        gap: 14px;
        padding-bottom: 13px;
    }
    .service-card {
        min-width: 84vw;
        max-width: 96vw;
        flex: 0 0 auto;
        min-height: 180px;
    }
}

/* Service Card: EQUAL WIDTH & HEIGHT FOR ALL, stretch for boxes with less content */
.service-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #efefef;
    box-shadow: 0 4px 18px 0 rgba(45,48,65,0.04);
    padding: 36px 24px 28px 24px;
    text-align: center;
    transition: box-shadow 0.21s, transform 0.14s;
    position: relative;

    min-width: 0;
    width: 100%;
    min-height: 330px;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    /* No margin-bottom is needed since .services-list-grid row-gap provides vertical space */
}

.service-card:hover, .service-card:focus {
    box-shadow: 0 8px 36px 0 rgba(34,34,46,0.11);
    transform: translateY(-6px) scale(1.037);
    border-color: #d9dde4;
}
.service-icon-image {
    margin-bottom: 22px;
}
.service-icon-image i {
    font-size: 2.8em;
    color: #36395a;
    opacity: 0.85;
}
.service-icon-image img {
    width: 66px;
    height: 66px;
    max-width: 98%;
    max-height: 70px;
    object-fit: contain;
    margin: 0 auto;
    background: none;
    border: none;
    box-shadow: none;
    filter: grayscale(30%);
}
.service-card h3 {
    font-size: 1.22em;
    margin: 10px 0 14px 0;
    font-weight: 700;
    color: #262739;
    letter-spacing: 0.03em;
}
.service-card p {
    color: #585f6d;
    font-size: 1.055em;
    margin: 0;
    line-height: 1.7;
    flex-grow: 1;
    overflow-wrap: break-word;
    word-break: break-word;
    display: block;
}

/* Values Section */
.values-section {
    background: #fff;
    padding: 54px 0 26px 0;
    text-align: center;
    border-top: 1px solid #ececef;
}
.values-section h2 {
    font-size: 2em;
    color: #212124;
    margin-bottom: 8px;
    font-weight: 600;
}
.values-grid {
    margin: 18px auto 0 auto;
    max-width: 900px;
}
.value-card {
    background: #f8f9fc;
    border: 1px solid #dedee4;
    border-radius: 10px;
    padding: 30px 22px 21px 22px;
    font-size: 1.01em;
    color: #334;
    min-height: 110px;
    text-align: left;
    margin: 0 0 18px 0;
    box-shadow: 0 3px 14px 0 rgba(200,200,214,0.08);
    transition: box-shadow 0.17s, transform 0.15s;
}
.value-card:hover,
.value-card:focus {
    box-shadow: 0 8px 30px 0 rgba(45,45,82,0.09);
    transform: translateY(-5px) scale(1.025);
    border-color: #cbcbdd;
}
.value-card strong {
    font-weight: 600;
    color: #22253b;
}
@media (max-width: 767px) {
    .service-card { min-height: 200px; }
    .values-grid .col-md-6,
    .values-grid .col-lg-4 {
        margin-bottom: 18px;
    }
    .values-section {
        padding: 32px 0 14px 0;
    }
}

</style>

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
