<?php


namespace Test\Subscription\Plugin;

use \Magento\Framework\Api\SearchCriteriaBuilder;
use \Magento\Framework\Api\SortOrder;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use \Magento\Framework\Exception\InputException;
use \Magento\Customer\Model\Session;
use \Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order;

class LoginPost
{

    /**
     * Assuming the subscription plan is controlled by SKU
     */
    protected const SUBSCRIPTIONSKU_60 = "60dayssubscription";

    /**
     * @var Session
     */
    protected Session $_customerSession;
    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultRedirectFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    /**
     * @var SortOrder
     */
    private \Magento\Framework\Api\SortOrder $sortOrderBuilder;
    /**
     * @var Order
     */
    private Order $orderResource;
    /**
     * @var OrderFactory
     */
    private OrderFactory $orderModel;


    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrder $sortOrderBuilder,
        Session $session,
        ResultFactory $resultRedirectFactory,
        OrderFactory $orderModel,
        Order $orderResource

    ){
        $this->sortOrderBuilder= $sortOrderBuilder;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_customerSession = $session;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderModel = $orderModel;
        $this->orderResource = $orderResource;
    }

    /**
     * @param \Magento\Customer\Controller\Account\LoginPost $subject
     * @param $result
     * @return Redirect|ResultInterface|mixed
     * @throws InputException
     */
    public function afterExecute(
        \Magento\Customer\Controller\Account\LoginPost $subject,
        $result
    ) {


        if  (!$this->_customerSession->isLoggedIn()) { return $result; }

        $customerId = $this->_customerSession->getCustomerId();
        $sortOrder = $this->sortOrderBuilder->setField('created_at')->setDirection(SortOrder::SORT_DESC);
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('customer_id', $customerId, 'eq')->create();
        $searchCriteria = $searchCriteria->setSortOrders([$sortOrder] );
        $searchCriteria->setCurrentPage(1);
        $searchCriteria->setPageSize(1);

        $orders = $this->orderRepository->getList($searchCriteria);
        $orderId = 0;
        foreach ($orders as $order) {
            $orderId = $order->getId();
        }

        $orderModel  = $this->orderModel->create();
        $this->orderResource->load($orderModel, $orderId);


        $orderDate = $orderModel->getCreatedAt();
        $activePlan =  false;
        foreach ($orderModel->getAllVisibleItems() as $item) {
            $activePlan = self::SUBSCRIPTIONSKU_60 === $item->getSku() && $this->isSubscriptionActive($orderDate);
        }

        if ($activePlan) {
            return $result;
        }

        $result = $this->resultRedirectFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $result->setPath("/plans/subscribe");

        return $result;

    }

    /**
     * @param $orderDate
     * @return bool
     */
    private function isSubscriptionActive ($orderDate): bool
    {

        $expirationDate = date ( 'Y-m-d' , strtotime ( $orderDate . ' + 60 days' ));
        $date_now = date("Y-m-d");

        return $date_now <= $expirationDate;
    }
}
